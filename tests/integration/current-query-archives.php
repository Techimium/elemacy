<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Pagination\PaginationContextSigner;
use Elemacy\Loop\Pagination\PublicRenderPolicy;
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
 * A "Current Query" loop inside an archive or search template keeps that
 * archive's own constraints (term, author, date, search term) on page one
 * and on every AJAX page, rebuilt from server-verified facts only.
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

    public function testCategoryArchiveKeepsItsTermOnPageOneAndOnAjaxPages(): void
    {
        $f = $this->fixture;
        $this->request->visit(get_term_link($f['cat_a']));
        $this->assertResolvedLocation('archive', $f['archive']);

        self::assertSame([$f['p2']], $this->pageOne($f['archive'], $f['settings']), 'Page one must contain only the newest post in this category.');
        self::assertSame([$f['p2']], $this->renderedItems($f['archive']), 'The widget render path must use the same constrained query.');
        self::assertSame([$f['p1']], $this->ajaxPage($f['archive'], 2), 'AJAX page two must stay inside the category.');
    }

    public function testAuthorArchiveKeepsItsAuthor(): void
    {
        $f = $this->fixture;
        $this->request->visit(get_author_posts_url($f['u1']));
        $this->assertResolvedLocation('archive', $f['archive']);

        self::assertSame([$f['p3']], $this->pageOne($f['archive'], $f['settings']));
        self::assertSame([$f['p1']], $this->ajaxPage($f['archive'], 2));
    }

    public function testDateArchiveKeepsItsMonth(): void
    {
        $f = $this->fixture;
        $this->request->visit(get_month_link(2025, 3));
        $this->assertResolvedLocation('archive', $f['archive']);

        self::assertSame([$f['p2']], $this->pageOne($f['archive'], $f['settings']));
        self::assertSame([$f['p1']], $this->ajaxPage($f['archive'], 2));
    }

    public function testSearchResultsKeepTheSearchTerm(): void
    {
        $f = $this->fixture;
        $this->request->visit(add_query_arg('s', $f['token'], home_url('/')));
        $this->assertResolvedLocation('search', $f['search']);

        $first = $this->pageOne($f['search'], $f['settings']);
        $second = $this->ajaxPage($f['search'], 2);

        self::assertCount(1, $first);
        self::assertCount(1, $second);
        self::assertNotSame($first, $second);
        self::assertEqualsCanonicalizing([$f['p1'], $f['p3']], array_merge($first, $second), 'Only the two posts carrying the search token may appear.');
    }

    public function testAnOrdinaryPageCannotSupplyAnArchiveQuery(): void
    {
        $f = $this->fixture;
        $this->request->visit(get_permalink($f['page']));

        try {
            $this->policy()->eligible_items($f['settings'], $f['page']);
            self::fail('A Current Query loop on an ordinary page must not render an unconstrained list of posts.');
        } catch (ValidationException $error) {
            self::assertStringContainsString('Current Query', $this->policy()->describe_ineligibility($f['settings'], $f['page']));
        }

        self::assertSame([], $this->renderedItems($f['page']));
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

    /** @return int[] */
    private function pageOne(int $owner, array $settings): array
    {
        return array_map(
            static fn ($item): int => (int) $item->get_field('ID'),
            $this->policy()->eligible_items($settings, $owner)->items
        );
    }

    /** @return int[] */
    private function ajaxPage(int $owner, int $page): array
    {
        $definition = (new PublishedLoopDefinitionResolver($this->policy()))->find($owner, self::WIDGET_ID);
        $placement = PlacementResolverRegistry::instance()->resolve_current($owner);
        self::assertNotNull($placement, 'The template must resolve as the current Theme Builder placement.');
        self::assertSame('theme_builder', $placement['type']);

        $context = (new PaginationContextSigner())->sign($owner, self::WIDGET_ID, $definition->fingerprint(), $placement['type'], $placement['facts']);
        $payload = [
            'version' => 1, 'owner_id' => $owner, 'widget_id' => self::WIDGET_ID,
            'page' => $page, 'fingerprint' => $definition->fingerprint(), 'context' => $context,
        ];
        $response = (array) json_decode(NativeAjaxRequest::loop(wp_json_encode($payload), ''), true);
        self::assertTrue($response['success'] ?? false, 'AJAX page ' . $page . ' must succeed: ' . wp_json_encode($response));

        return $this->itemIds((string) ($response['data']['items'] ?? ''));
    }

    /** @return int[] Post IDs the widget's own frontend render emitted, in order. */
    private function renderedItems(int $owner): array
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

        return $this->itemIds($html);
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

        $loop = $this->documents->create('Loop item ' . $suffix, 'loop');
        $settings = [
            'data_source' => 'posts', 'post_type' => 'current_query', 'template_id' => $loop,
            'pagination_type' => 'numbers', 'pagination_ajax' => 'yes',
        ];
        $archive = $this->documents->create('Archive ' . $suffix, 'archive', 'publish', $this->grid($settings));
        $search = $this->documents->create('Search ' . $suffix, 'search', 'publish', $this->grid($settings));

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

        return compact('token', 'cat_a', 'cat_b', 'u1', 'u2', 'p1', 'p2', 'p3', 'p4', 'loop', 'settings', 'archive', 'search')
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
