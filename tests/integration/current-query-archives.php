<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Pagination\PaginationContextSigner;
use Elemacy\Loop\Pagination\PublicRenderPolicy;
use Elemacy\Modules\Widgets\DataSources\CurrentQueryDataSource;
use Elemacy\Loop\Pagination\PublishedLoopDefinitionResolver;
use Elemacy\Modules\ThemeBuilder\Services\LocationRegistry;
use Elemacy\Loop\Placement\PlacementResolverRegistry;
use Elemacy\TemplateLibrary\TemplateResolver;
use Elemacy\TemplateLibrary\TypeRegistry;
use Elemacy\Tests\Integration\Fixtures\NativeAjaxRequest;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;
use Elementor\Plugin;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/NativeAjaxRequest.php';
require_once __DIR__ . '/fixtures/RequestSimulator.php';

/**
 * The Current Query data source lists exactly what the page's own main query
 * returns, on any page, and its later pages are the page's own paginated URLs
 * (never the signed-definition endpoint): the same posts a visitor gets by
 * visiting each URL, including constraints no archive variable expresses.
 */
final class CurrentQueryArchivesTest extends IntegrationTestCase
{
    private const WIDGET_ID = 'eac7001';

    private RequestSimulator $request;
    private array $fixture = [];
    private array $posts = [];
    private array $terms = [];
    private array $users = [];
    private $previous_per_page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new RequestSimulator();
        $this->previous_per_page = get_option('posts_per_page');
        update_option('posts_per_page', 1);

        try {
            $this->fixture = $this->createFixture();
        } catch (\Throwable $error) {
            $this->cleanupOwned();
            throw $error;
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->request->restore();
            $this->cleanupOwned();
        } finally {
            parent::tearDown();
        }
    }

    public function testCategoryArchiveListsItsOwnPostsOnEveryPageUrl(): void
    {
        $f = $this->fixture;
        $url = get_term_link($f['cat_a']);

        $this->request->visit($url);
        $this->assertResolvedLocation('archive', $f['archive']);
        self::assertSame([$f['p2']], $this->renderedItems($f['archive']), 'Page one must list only the newest post of the category.');

        $this->request->visit(add_query_arg('paged', 2, $url));
        self::assertSame([$f['p1']], $this->renderedItems($f['archive']), 'Page two must be what the page-two URL itself shows.');
    }

    public function testAuthorArchiveListsItsAuthorsPosts(): void
    {
        $f = $this->fixture;
        $url = get_author_posts_url($f['u1']);

        $this->request->visit($url);
        $this->assertResolvedLocation('archive', $f['archive']);
        self::assertSame([$f['p3']], $this->renderedItems($f['archive']));

        $this->request->visit(add_query_arg('paged', 2, $url));
        self::assertSame([$f['p1']], $this->renderedItems($f['archive']));
    }

    public function testDateArchiveListsItsMonth(): void
    {
        $f = $this->fixture;
        $url = get_month_link(2025, 3);

        $this->request->visit($url);
        $this->assertResolvedLocation('archive', $f['archive']);
        self::assertSame([$f['p2']], $this->renderedItems($f['archive']));

        $this->request->visit(add_query_arg('paged', 2, $url));
        self::assertSame([$f['p1']], $this->renderedItems($f['archive']));
    }

    public function testSearchResultsListTheSearchMatches(): void
    {
        $f = $this->fixture;
        $url = add_query_arg('s', $f['token'], home_url('/'));

        $this->request->visit($url);
        $this->assertResolvedLocation('search', $f['search']);
        $first = $this->renderedItems($f['search']);

        $this->request->visit(add_query_arg('paged', 2, $url));
        $second = $this->renderedItems($f['search']);

        self::assertCount(1, $first);
        self::assertCount(1, $second);
        self::assertNotSame($first, $second);
        self::assertEqualsCanonicalizing([$f['p1'], $f['p3']], array_merge($first, $second), 'Only the two posts carrying the search token may appear.');
    }

    public function testAMainQueryNarrowedByAPluginKeepsItsConstraintsOnEveryPage(): void
    {
        $f = $this->fixture;
        // Category A holds p1, p2 (ranked) and p5 (no rank). A plugin narrows and sorts the
        // category archive with a meta query and a meta sort no archive variable expresses.
        $narrow = static function (\WP_Query $query): void {
            if ($query->is_main_query() && $query->is_category()) {
                $query->set('meta_key', 'elemacy_cq_rank');
                $query->set('orderby', 'meta_value_num');
                $query->set('order', 'ASC');
            }
        };
        add_action('pre_get_posts', $narrow);

        try {
            $url = get_term_link($f['cat_a']);
            $this->request->visit($url);
            self::assertSame([$f['p1']], $this->renderedItems($f['archive']), 'Page one follows the plugin\'s own sort.');

            $this->request->visit(add_query_arg('paged', 2, $url));
            self::assertSame([$f['p2']], $this->renderedItems($f['archive']), 'Page two keeps the same constraint and sort.');

            $this->request->visit(add_query_arg('paged', 3, $url));
            self::assertSame([], $this->renderedItems($f['archive']), 'The unranked post stays excluded, so there is no third page.');
        } finally {
            remove_action('pre_get_posts', $narrow);
        }
    }

    public function testTheBlogPageAndItsPagedUrlListTheSitesLatestPosts(): void
    {
        $f = $this->fixture;
        $expected = static function (int $page): array {
            $query = new \WP_Query(['post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1, 'paged' => $page, 'fields' => 'ids', 'ignore_sticky_posts' => true]);

            return array_map('intval', $query->posts);
        };

        $this->request->visit(home_url('/'));
        self::assertSame($expected(1), $this->renderedItems($f['archive']), 'The blog page lists what its own query returns.');

        $this->request->visit(add_query_arg('paged', 2, home_url('/')));
        self::assertSame($expected(2), $this->renderedItems($f['archive']), 'Its page-two URL lists the next post.');
    }

    public function testASingularPageRendersItsOwnQueryWithoutError(): void
    {
        $f = $this->fixture;
        $this->request->visit(get_permalink($f['page']));

        self::assertSame([$f['page']], $this->renderedItems($f['page']));
    }

    public function testAQueryWithNoPostsRendersNothing(): void
    {
        $f = $this->fixture;
        $this->request->visit(add_query_arg('s', 'nomatch' . $f['token'], home_url('/')));

        self::assertSame([], $this->renderedItems($f['search']));
    }

    public function testClassicPaginationLinksAreThePagesOwnUrlsAndTheLoopIsMarkedForPageMode(): void
    {
        $f = $this->fixture;
        $this->request->visit(get_term_link($f['cat_a']));

        $html = $this->renderedHtml($f['archive']);

        self::assertStringContainsString('data-elemacy-loop-mode="page"', $html);
        self::assertStringContainsString('data-elemacy-loop-id="' . $f['archive'] . '-' . self::WIDGET_ID . '"', $html);
        self::assertStringNotContainsString('data-elemacy-loop-request', $html, 'A page-URL loop carries no signed endpoint payload.');
        self::assertMatchesRegularExpression('#href="[^"]*(?:/page/2/|paged=2)[^"]*"#', $html, 'Pagination links are the page\'s own paginated URLs.');
    }

    public function testTheLoopEndpointRefusesACurrentQueryWidget(): void
    {
        $f = $this->fixture;
        $this->request->visit(get_term_link($f['cat_a']));

        $definition = (new PublishedLoopDefinitionResolver($this->policy()))->find($f['archive'], self::WIDGET_ID);
        $payload = [
            'version' => 1, 'owner_id' => $f['archive'], 'widget_id' => self::WIDGET_ID,
            'page' => 2, 'fingerprint' => $definition->fingerprint(), 'context' => '',
        ];
        $response = (array) json_decode(NativeAjaxRequest::loop(wp_json_encode($payload), ''), true);

        self::assertFalse($response['success'] ?? true, 'The endpoint must not serve a Current Query widget: ' . wp_json_encode($response));
        self::assertArrayNotHasKey('items', (array) ($response['data'] ?? []));
    }

    public function testASavedPostsWidgetWithAStaleCurrentQueryPostTypeRendersNothing(): void
    {
        $f = $this->fixture;
        $stale = ['data_source' => 'posts', 'post_type' => 'current_query'] + $f['settings'];
        $host = $this->documents->create_everywhere('Stale ' . $f['token'], 'archive', 'publish', $this->grid($stale));
        $this->request->visit(get_term_link($f['cat_a']));

        try {
            $this->policy()->eligible_items($stale, $host);
            self::fail('Posts must reject current_query as a post type.');
        } catch (ValidationException $error) {
            self::assertNotSame('', $error->getMessage());
        }

        self::assertSame([], $this->renderedItems($host));
    }

    public function testCurrentQueryIsItsOwnRegisteredSourceNextToPosts(): void
    {
        $sources = LoopDataSourceRegistry::instance()->all();

        self::assertInstanceOf(CurrentQueryDataSource::class, $sources['current_query'] ?? null);
        self::assertSame(['posts', 'current_query'], array_slice(array_keys($sources), 0, 2));
    }

    public function testTheQuerySectionKeepsPostsControlsToPostsAndGivesCurrentQueryNoQueryControls(): void
    {
        $recorder = new class([], null) extends \Elemacy\Elementor\Widgets\BaseWidget {
            public array $recorded = [];

            public function get_name()
            {
                return 'elemacy-current-query-recorder';
            }

            public function add_control($id, array $args, $options = [])
            {
                $this->recorded[$id] = $args;

                return true;
            }
        };

        foreach (LoopDataSourceRegistry::instance()->all() as $source) {
            if (in_array($source->get_key(), ['posts', 'current_query'], true)) {
                $source->register_controls($recorder);
            }
        }
        $controls = $recorder->recorded;

        self::assertArrayNotHasKey('current_query', $controls['post_type']['options'], 'Posts lists real public post types only.');
        self::assertArrayHasKey('post', $controls['post_type']['options']);
        self::assertSame('Post Type', $controls['post_type']['label']);

        foreach (['post_type', 'posts_per_page', 'offset', 'orderby', 'order', 'exclude_current_post'] as $name) {
            self::assertSame(['data_source' => 'posts'], $controls[$name]['condition'], $name . ' belongs to the Posts source alone.');
        }

        self::assertSame(['current_query_notice'], array_values(array_diff(array_keys($controls), ['post_type', 'posts_per_page', 'offset', 'orderby', 'order', 'exclude_current_post'])), 'Current Query registers only its informational notice.');
        self::assertSame(['data_source' => 'current_query'], $controls['current_query_notice']['condition']);
    }

    public function testLoopItemPreviewUsesTheMostRecentPublicPost(): void
    {
        $recent = get_posts([
            'post_type' => \Elemacy\Support\PostTypes::public_names(), 'post_status' => 'publish',
            'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids',
        ]);
        $item = (new CurrentQueryDataSource())->resolve_preview_item([]);

        self::assertNotNull($item);
        self::assertSame((int) $recent[0], (int) $item->get_field('ID'));
    }

    private function assertResolvedLocation(string $type, int $expected): void
    {
        self::assertSame($type, LocationRegistry::instance()->current_type(), 'The simulated request must be recognized as a ' . $type . ' location.');
        self::assertSame($expected, TemplateResolver::instance()->resolve($type), 'The fixture template must be the one resolved for this request.');
    }

    private function policy(): PublicRenderPolicy
    {
        return new PublicRenderPolicy(TypeRegistry::instance(), LoopDataSourceRegistry::instance());
    }

    /** @return int[] Post IDs the widget's own frontend render emitted, in order. */
    private function renderedItems(int $owner): array
    {
        return $this->itemIds($this->renderedHtml($owner));
    }

    /** The widget's own frontend render for the current simulated request. */
    private function renderedHtml(int $owner): string
    {
        $document = Plugin::$instance->documents->get_doc_for_frontend($owner);
        self::assertNotNull($document);
        Plugin::$instance->documents->switch_to_document($document);

        try {
            $raw = $this->findWidget($document->get_elements_data(), self::WIDGET_ID);
            self::assertNotNull($raw, 'Fixture widget not found in the saved document.');
            $widget = Plugin::$instance->elements_manager->create_element_instance($raw);
            $method = new \ReflectionMethod($widget, 'render');
            $method->setAccessible(true);
            ob_start();
            try {
                $method->invoke($widget);
            } finally {
                $html = ob_get_clean();
            }
        } finally {
            Plugin::$instance->documents->restore_document();
        }

        return $html;
    }

    /** @return int[] */
    private function itemIds(string $html): array
    {
        preg_match_all('/class="elemacy-loop-item elemacy-loop-item-([A-Za-z0-9_-]+)"/', $html, $matches);

        return array_values(array_unique(array_map(
            static fn (string $selector): int => (int) preg_replace('/-[0-9a-f]{12}$/', '', $selector),
            $matches[1]
        )));
    }

    private function findWidget(array $elements, string $widget_id): ?array
    {
        foreach ($elements as $element) {
            if (($element['id'] ?? null) === $widget_id) {
                return $element;
            }
            if (!empty($element['elements'])) {
                $found = $this->findWidget($element['elements'], $widget_id);
                if (null !== $found) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function createFixture(): array
    {
        $suffix = bin2hex(random_bytes(4));
        $token = 'zebraquux' . $suffix;

        $cat_a = $this->term('Alpha ' . $suffix);
        $cat_b = $this->term('Beta ' . $suffix);
        $u1 = $this->user('elemacy_cq_' . $suffix . 'a');
        $u2 = $this->user('elemacy_cq_' . $suffix . 'b');

        $p1 = $this->post('Alpha one ' . $token, $cat_a, $u1, '2025-03-10 10:00:00');
        $p2 = $this->post('Alpha two ' . $suffix, $cat_a, $u2, '2025-03-12 10:00:00');
        $p3 = $this->post('Beta one ' . $token, $cat_b, $u1, '2025-04-05 10:00:00');
        $p4 = $this->post('Beta two ' . $suffix, $cat_b, $u2, '2025-04-07 10:00:00');
        $p5 = $this->post('Alpha unranked ' . $suffix, $cat_a, $u2, '2025-03-01 10:00:00');
        update_post_meta($p1, 'elemacy_cq_rank', 1);
        update_post_meta($p2, 'elemacy_cq_rank', 2);

        $loop = $this->documents->create('Loop item ' . $suffix, 'loop');
        $settings = [
            'data_source' => 'current_query', 'template_id' => $loop,
            'pagination_type' => 'numbers', 'pagination_ajax' => 'yes',
        ];
        $archive = $this->documents->create_everywhere('Archive ' . $suffix, 'archive', 'publish', $this->grid($settings));
        $search = $this->documents->create_everywhere('Search ' . $suffix, 'search', 'publish', $this->grid($settings));

        $page = Plugin::instance()->documents->create('wp-page', [
            'post_type' => 'page', 'post_title' => 'Host ' . $suffix, 'post_status' => 'draft',
        ]);
        if (is_wp_error($page)) {
            throw new \RuntimeException($page->get_error_message());
        }
        $this->posts[] = $page->get_main_id();
        if (!$page->save(['elements' => $this->grid($settings), 'settings' => ['post_status' => 'publish']])) {
            throw new \RuntimeException('Could not save the ordinary host page.');
        }

        return compact('token', 'cat_a', 'cat_b', 'u1', 'u2', 'p1', 'p2', 'p3', 'p4', 'p5', 'loop', 'settings', 'archive', 'search')
            + ['page' => $page->get_main_id()];
    }

    private function grid(array $settings): array
    {
        return [[
            'id' => 'eac7000', 'elType' => 'container', 'settings' => [],
            'elements' => [[
                'id' => self::WIDGET_ID, 'elType' => 'widget', 'widgetType' => 'elemacy-loop-grid',
                'settings' => $settings, 'elements' => [],
            ]],
        ]];
    }

    private function post(string $title, int $category, int $author, string $date): int
    {
        $id = wp_insert_post([
            'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $title,
            'post_content' => 'Current query fixture.', 'post_author' => $author,
            'post_date' => $date, 'post_date_gmt' => get_gmt_from_date($date), 'post_category' => [$category],
        ], true);
        if (is_wp_error($id)) {
            throw new \RuntimeException($id->get_error_message());
        }
        $this->posts[] = (int) $id;

        return (int) $id;
    }

    private function term(string $name): int
    {
        $term = wp_insert_term($name, 'category');
        if (is_wp_error($term)) {
            throw new \RuntimeException($term->get_error_message());
        }
        $this->terms[] = (int) $term['term_id'];

        return (int) $term['term_id'];
    }

    private function user(string $login): int
    {
        $user = wp_insert_user([
            'user_login' => $login, 'user_pass' => wp_generate_password(32),
            'user_email' => $login . '@example.invalid', 'role' => 'author',
        ]);
        if (is_wp_error($user)) {
            throw new \RuntimeException($user->get_error_message());
        }
        $this->users[] = (int) $user;

        return (int) $user;
    }

    private function cleanupOwned(): void
    {
        foreach (array_reverse($this->posts) as $id) {
            wp_delete_post($id, true);
        }
        foreach ($this->terms as $id) {
            wp_delete_term($id, 'category');
        }
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ($this->users as $id) {
            wp_delete_user($id);
        }
        $this->posts = $this->terms = $this->users = [];
        update_option('posts_per_page', $this->previous_per_page);
    }
}
