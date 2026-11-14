<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Access\PaginationContextSigner;
use Elemacy\Loop\Pagination\PaginationRenderer;
use Elemacy\Loop\Access\PublicRenderPolicy;
use Elemacy\Loop\Access\PublishedLoopDefinitionResolver;
use Elemacy\Loop\Placement\PlacementResolverRegistry;
use Elemacy\Modules\LoopBuilder\DataSources\PostsDataSource;
use Elemacy\Modules\LoopBuilder\Widgets\LoopGrid\LoopGrid;
use Elemacy\TemplateLibrary\TypeRegistry;
use Elemacy\Loop\Access\PublicLoopLimits;
use Elemacy\Tests\Integration\Fixtures\NativeAjaxRequest;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;
use Elementor\Plugin;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/NativeAjaxRequest.php';
require_once __DIR__ . '/fixtures/RequestSimulator.php';

/**
 * Load More and Infinite Scroll on Loop Grid: the server side of both — the
 * controls, the next-page link and companions, the wrapper attributes the
 * script reads, the endpoint's `next_page`, and the page-size bound. The
 * in-browser behaviour is exercised by tests/e2e/run-loop-load-more.mjs.
 */
final class LoopLoadMoreTest extends IntegrationTestCase
{
    private const WIDGET_ID = 'eac8001';

    private RequestSimulator $request;
    private array $posts = [];
    private array $terms = [];
    private array $users = [];
    private int $template = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new RequestSimulator();
        $suffix = bin2hex(random_bytes(4));
        $this->template = $this->documents->create('Load more item ' . $suffix, 'loop');

        foreach (range(1, 5) as $i) {
            $term = wp_insert_term('Load more term ' . $i . ' ' . $suffix, 'category');
            $this->terms[] = (int) $term['term_id'];
            $user = wp_insert_user([
                'user_login' => 'elemacy_lm_' . $suffix . $i, 'user_pass' => wp_generate_password(32),
                'user_email' => 'elemacy_lm_' . $suffix . $i . '@example.invalid', 'role' => 'author',
            ]);
            $this->users[] = (int) $user;
            $this->posts[] = (int) wp_insert_post([
                'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Load more post ' . $i . ' ' . $suffix,
                'post_content' => 'Load more fixture.', 'post_author' => $user, 'post_category' => [$term['term_id']],
            ]);
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->request->restore();
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
        } finally {
            parent::tearDown();
        }
    }

    // ---- Controls ----------------------------------------------------------

    public function testThePaginationSectionOffersTheIncrementalTypesAndTheirTexts(): void
    {
        $widget = new class([], null) extends LoopGrid {
            public array $recorded = [];

            public function start_controls_section($section_id, array $args = [])
            {
            }

            public function end_controls_section()
            {
            }

            public function add_control($id, array $args, $options = [])
            {
                $this->recorded[$id] = $args;

                return true;
            }

            public function pagination(): array
            {
                \Elemacy\Modules\LoopBuilder\Widgets\Controls\PaginationSection::register($this);

                return $this->recorded;
            }
        };
        $controls = $widget->pagination();

        self::assertSame(
            ['', 'numbers', 'prev_next', 'numbers_and_prev_next', 'load_more', 'infinite_scroll'],
            array_keys($controls['pagination_type']['options'])
        );
        self::assertSame(['numbers', 'prev_next', 'numbers_and_prev_next'], $controls['pagination_ajax']['condition']['pagination_type'], 'The AJAX switch is offered for the link types only.');
        foreach (['load_more_text' => 'Load More', 'load_more_loading_text' => 'Loading…'] as $name => $default) {
            self::assertSame(['pagination_type' => ['load_more', 'infinite_scroll']], $controls[$name]['condition']);
            self::assertSame($default, $controls[$name]['default']);
        }
        self::assertSame(['pagination_type' => ['load_more', 'infinite_scroll']], $controls['load_more_end_text']['condition']);
    }

    // ---- Markup ------------------------------------------------------------

    public function testTheIncrementalControlIsARealNextLinkStyledLikeAPageLink(): void
    {
        $this->request->visit(home_url('/'));
        $html = $this->render(['pagination_type' => 'load_more', 'load_more_text' => 'More please'], 3, 1);

        self::assertMatchesRegularExpression('#<a class="page-numbers elemacy-load-more" rel="next" href="[^"]*(?:/page/2/|paged=2)[^"]*">More please</a>#', $html);
        self::assertStringNotContainsString('<span', $html, 'No page numbers are shown.');
        self::assertSame(1, substr_count($html, '<a '), 'Exactly one link: the next page.');
    }

    public function testTheLoadControlFallsBackToDefaultTextAndIsAbsentOnTheLastPage(): void
    {
        $this->request->visit(home_url('/'));

        self::assertStringContainsString('>Load More</a>', $this->render(['pagination_type' => 'infinite_scroll', 'load_more_text' => ''], 3, 2));
        self::assertSame('', $this->render(['pagination_type' => 'load_more'], 3, 3), 'No link after the last page.');
        self::assertSame('', $this->render(['pagination_type' => 'load_more'], 1, 1));
    }

    public function testTheLinkTypesRenderExactlyAsBefore(): void
    {
        $this->request->visit(home_url('/'));
        $html = $this->render(['pagination_type' => 'numbers'], 3, 2);

        self::assertStringContainsString('page-numbers current', $html);
        self::assertStringNotContainsString('elemacy-load-more', $html);
    }

    public function testTheStatusRegionAndEndMessageAreRenderedOnceOutsideTheLink(): void
    {
        ob_start();
        (new PaginationRenderer())->render_incremental_status(['load_more_end_text' => "That's all", 'load_more_loading_text' => 'Please wait']);
        $html = ob_get_clean();

        self::assertStringContainsString('role="status" aria-live="polite" data-loading-text="Please wait" data-loaded-text="More items loaded."', $html);
        self::assertStringContainsString('<p class="elemacy-load-more-end" hidden>That&#039;s all</p>', $html);

        ob_start();
        (new PaginationRenderer())->render_incremental_status([]);
        self::assertStringNotContainsString('elemacy-load-more-end', ob_get_clean(), 'No end message element unless the author set one.');
    }

    // ---- The widget --------------------------------------------------------

    public function testAnEndpointSourceCarriesTheSignedRequestAndTheRenderedPage(): void
    {
        $owner = $this->hostPage($this->postsGrid('load_more'));
        $this->request->visit(get_permalink($owner));

        $html = $this->renderWidget($owner);

        self::assertStringContainsString('data-elemacy-loop-style="load_more" data-elemacy-loop-page="1"', $html);
        self::assertStringContainsString('data-elemacy-loop-request="', $html);
        self::assertStringNotContainsString('data-elemacy-loop-mode="page"', $html);
        self::assertStringContainsString('elemacy-load-more', $html);
        self::assertMatchesRegularExpression('/class="elemacy-loop-item elemacy-loop-item-[^"]+" data-elemacy-item="\d+"/', $html, 'Each item carries its own identity.');
    }

    public function testCurrentQueryCarriesNoSignedRequestAndUsesThePageUrls(): void
    {
        $owner = $this->hostPage($this->postsGrid('infinite_scroll', ['data_source' => 'current_query']));
        $this->request->visit(home_url('/'));

        $html = $this->renderWidget($owner);

        self::assertStringContainsString('data-elemacy-loop-style="infinite_scroll"', $html);
        self::assertStringContainsString('data-elemacy-loop-mode="page"', $html);
        self::assertStringNotContainsString('data-elemacy-loop-request', $html);
    }

    public function testALinkTypeGridKeepsItsExistingStyleAndSwitch(): void
    {
        $owner = $this->hostPage($this->postsGrid('numbers', ['pagination_ajax' => 'yes']));
        $this->request->visit(get_permalink($owner));
        $html = $this->renderWidget($owner);

        self::assertStringContainsString('data-elemacy-loop-style="numbers"', $html);
        self::assertStringContainsString('elemacy-ajax-pagination', $html);

        $plain = $this->hostPage($this->postsGrid('numbers'));
        $this->request->visit(get_permalink($plain));
        self::assertStringNotContainsString('elemacy-ajax-pagination', $this->renderWidget($plain), 'Numbers without the AJAX switch stays a plain grid.');
    }

    public function testALaterPaginatedUrlRendersThatPageOfThePostsGrid(): void
    {
        $settings = $this->postsGrid('load_more');
        $per_page = $settings[0]['elements'][0]['settings']['posts_per_page'];
        $owner = $this->hostPage($settings);
        $second = array_map('strval', get_posts([
            'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => $per_page, 'paged' => 2,
            'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids', 'ignore_sticky_posts' => true,
        ]));

        $this->request->visit(add_query_arg('paged', 2, get_permalink($owner)));
        $html = $this->renderWidget($owner);

        self::assertSame($second, $this->itemIds($html), 'Opening the page-two URL shows page two, not page one again.');
        self::assertStringContainsString('data-elemacy-loop-page="2"', $html);
    }

    public function testAGridWithoutPaginationShowsTheSameItemsOnEveryUrl(): void
    {
        $owner = $this->hostPage($this->postsGrid(''));
        $this->request->visit(get_permalink($owner));
        $first = $this->itemIds($this->renderWidget($owner));

        $this->request->visit(add_query_arg('paged', 2, get_permalink($owner)));

        self::assertNotSame([], $first);
        self::assertSame($first, $this->itemIds($this->renderWidget($owner)));
    }

    public function testTheEditorPreviewShowsTheButton(): void
    {
        $owner = $this->hostPage($this->postsGrid('load_more'));
        $this->request->visit(get_permalink($owner));
        Plugin::$instance->editor->set_edit_mode(true);

        try {
            $html = $this->renderWidget($owner);
        } finally {
            Plugin::$instance->editor->set_edit_mode(false);
        }

        self::assertStringContainsString('elemacy-load-more', $html);
    }

    // ---- The endpoint ------------------------------------------------------

    /** @dataProvider endpointSources */
    public function testEndpointPagesChainToTheLastAndNeverRepeat(string $source): void
    {
        $owner = $this->hostPage($this->grid($this->sourceSettings($source) + ['pagination_type' => 'load_more']));
        $this->request->visit(get_permalink($owner));

        $seen = [];
        $page = 2;
        $responses = 0;
        do {
            $data = $this->endpointPage($owner, $page);
            $ids = $this->itemIds($data['items']);
            self::assertNotEmpty($ids, 'Page ' . $page . ' has items.');
            self::assertSame([], array_intersect($seen, $ids), 'No item repeats across pages.');
            $seen = array_merge($seen, $ids);
            self::assertSame($data['next_page'] > 0, '' !== trim($data['pagination']), 'The next-page link exists exactly while a next page does.');
            if ($data['next_page'] > 0) {
                self::assertSame($page + 1, $data['next_page']);
                self::assertStringContainsString('elemacy-load-more', $data['pagination']);
            }
            $page = $data['next_page'];
            $responses++;
        } while ($page > 0 && $responses < 20);

        self::assertSame(0, $page, 'The chain ends with next_page 0.');
        self::assertGreaterThan(1, $responses, 'The fixture spans several pages.');
    }

    public static function endpointSources(): array
    {
        return [['posts'], ['terms'], ['users']];
    }

    public function testTamperingWithALoadRequestIsRefusedLikeAnyOther(): void
    {
        $owner = $this->hostPage($this->postsGrid('load_more'));
        $this->request->visit(get_permalink($owner));

        $payload = $this->payload($owner, 2);
        $payload['fingerprint'] = str_repeat('0', 64);
        $response = (array) json_decode(NativeAjaxRequest::loop(wp_json_encode($payload), ''), true);

        self::assertFalse($response['success'] ?? true);
    }

    // ---- Page limit and labels ----------------------------------------------

    public function testAPageLimitEndsLoadMoreAndRefusesLaterPages(): void
    {
        $owner = $this->hostPage($this->postsGrid('load_more', ['pagination_page_limit' => 2]));
        $this->request->visit(get_permalink($owner));

        self::assertStringContainsString('elemacy-load-more', $this->renderWidget($owner));

        $last = $this->endpointPage($owner, 2);
        self::assertSame(0, $last['next_page'], 'The limit is the last page.');
        self::assertSame('', trim($last['pagination']), 'No Load More link remains.');

        $response = (array) json_decode(NativeAjaxRequest::loop(wp_json_encode($this->payload($owner, 3)), ''), true);
        self::assertFalse($response['success'] ?? true, 'A page past the limit is refused.');
    }

    public function testNumberedLinksStopAtThePageLimit(): void
    {
        $owner = $this->hostPage($this->postsGrid('numbers', ['pagination_page_limit' => 2]));
        $this->request->visit(get_permalink($owner));
        $html = $this->renderWidget($owner);

        self::assertMatchesRegularExpression('#(/2/|[?&](?:amp;|\#038;)?page=2)#', $html, 'A singular page\'s links are its own paged URLs.');
        self::assertDoesNotMatchRegularExpression('#(/3/|[?&](?:amp;|\#038;)?page=3)#', $html);
    }

    public function testAClassicUrlPastTheLimitShowsTheLastAllowedPage(): void
    {
        $settings = $this->postsGrid('numbers', ['pagination_page_limit' => 2]);
        $owner = $this->hostPage($settings);

        $this->request->visit(add_query_arg('paged', 2, get_permalink($owner)));
        $second = $this->itemIds($this->renderWidget($owner));
        $this->request->visit(add_query_arg('paged', 3, get_permalink($owner)));

        self::assertSame($second, $this->itemIds($this->renderWidget($owner)));
    }

    public function testNoPageLimitChangesNothing(): void
    {
        self::assertSame(9, PublicLoopLimits::effective_max_pages([], 9));
        self::assertSame(9, PublicLoopLimits::effective_max_pages(['pagination_page_limit' => ''], 9));
        self::assertSame(3, PublicLoopLimits::effective_max_pages(['pagination_page_limit' => 3], 9));
        self::assertSame(2, PublicLoopLimits::effective_max_pages(['pagination_page_limit' => 3], 2));
    }

    public function testCustomPreviousAndNextLabels(): void
    {
        $this->request->visit(home_url('/'));
        $html = $this->render(['pagination_type' => 'prev_next', 'pagination_prev_label' => 'Newer', 'pagination_next_label' => 'Older'], 3, 2);

        self::assertStringContainsString('>Newer</a>', $html);
        self::assertStringContainsString('>Older</a>', $html);
    }

    public function testEmptyLabelsKeepTheDefaults(): void
    {
        $this->request->visit(home_url('/'));
        $html = $this->render(['pagination_type' => 'prev_next', 'pagination_prev_label' => '', 'pagination_next_label' => ''], 3, 2);

        self::assertStringContainsString('« Previous', $html);
        self::assertStringContainsString('Next »', $html);
    }

    public function testCustomLabelsAreEscaped(): void
    {
        $this->request->visit(home_url('/'));
        $html = $this->render(['pagination_type' => 'prev_next', 'pagination_next_label' => '<b>Older</b>'], 3, 1);

        self::assertStringNotContainsString('<b>Older</b>', $html);
    }

    // ---- Page size ---------------------------------------------------------

    public function testAGridsFirstPageIsBoundedLikeItsLaterPages(): void
    {
        $captured = null;
        $observe = static function (\WP_Query $query) use (&$captured) {
            $captured = $query->get('posts_per_page');
        };
        $settings = ['post_type' => 'post', 'orderby' => 'date', 'order' => 'DESC', 'exclude_current_post' => 'no', 'posts_per_page' => 5000];

        add_action('pre_get_posts', $observe);
        try {
            (new PostsDataSource())->get_items($settings + ['pagination_type' => 'load_more', 'pagination_ajax' => 'no']);
            self::assertSame(100, $captured);

            $captured = null;
            (new PostsDataSource())->get_items($settings + ['pagination_type' => 'numbers', 'pagination_ajax' => 'no']);
            self::assertSame(100, $captured, 'A link-type grid without AJAX is bounded the same way.');
        } finally {
            remove_action('pre_get_posts', $observe);
        }
    }

    // ---- Helpers -----------------------------------------------------------

    private function render(array $settings, int $max_pages, int $current): string
    {
        ob_start();
        (new PaginationRenderer())->render($settings, $max_pages, $current);

        return ob_get_clean();
    }

    /** Posts shown three to a page across every published post. */
    private function postsGrid(string $type, array $extra = []): array
    {
        return $this->grid($extra + $this->sourceSettings('posts') + ['pagination_type' => $type]);
    }

    private function sourceSettings(string $source): array
    {
        $per_page = static fn (int $total): int => max(1, (int) ceil($total / 3));
        $settings = [
            'posts' => [
                'data_source' => 'posts', 'post_type' => 'post', 'posts_per_page' => $per_page((int) wp_count_posts('post')->publish),
                'orderby' => 'date', 'order' => 'DESC', 'exclude_current_post' => '', 'offset' => 0,
            ],
            'terms' => [
                'data_source' => 'terms', 'taxonomy' => 'category', 'terms_number' => $per_page((int) wp_count_terms(['taxonomy' => 'category', 'hide_empty' => false])),
                'terms_orderby' => 'id', 'terms_order' => 'ASC', 'hide_empty' => '',
            ],
            'users' => [
                'data_source' => 'users', 'users_roles' => ['author'], 'users_number' => $per_page(count(get_users(['role' => 'author', 'fields' => 'ID']))),
                'users_orderby' => 'ID', 'users_order' => 'ASC',
            ],
        ];

        return $settings[$source];
    }

    private function grid(array $settings): array
    {
        return [[
            'id' => 'eac8000', 'elType' => 'container', 'settings' => [],
            'elements' => [[
                'id' => self::WIDGET_ID, 'elType' => 'widget', 'widgetType' => 'elemacy-loop-grid',
                'settings' => $settings + ['template_id' => $this->template], 'elements' => [],
            ]],
        ]];
    }

    private function hostPage(array $elements): int
    {
        $page = Plugin::instance()->documents->create('wp-page', [
            'post_type' => 'page', 'post_title' => 'Load more host ' . bin2hex(random_bytes(3)), 'post_status' => 'draft',
        ]);
        if (is_wp_error($page)) {
            throw new \RuntimeException($page->get_error_message());
        }
        $this->posts[] = $page->get_main_id();
        if (!$page->save(['elements' => $elements, 'settings' => ['post_status' => 'publish']])) {
            throw new \RuntimeException('Could not save the host page.');
        }

        return $page->get_main_id();
    }

    /** The widget's own frontend render for the current simulated request. */
    private function renderWidget(int $owner): string
    {
        $document = Plugin::$instance->documents->get_doc_for_frontend($owner);
        self::assertNotNull($document);
        Plugin::$instance->documents->switch_to_document($document);

        try {
            $raw = $this->findWidget($document->get_elements_data());
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

    private function findWidget(array $elements): ?array
    {
        foreach ($elements as $element) {
            if (self::WIDGET_ID === ($element['id'] ?? null)) {
                return $element;
            }
            if (!empty($element['elements']) && null !== ($found = $this->findWidget($element['elements']))) {
                return $found;
            }
        }

        return null;
    }

    private function policy(): PublicRenderPolicy
    {
        return new PublicRenderPolicy(TypeRegistry::instance(), LoopDataSourceRegistry::instance());
    }

    private function payload(int $owner, int $page): array
    {
        $definition = (new PublishedLoopDefinitionResolver($this->policy()))->find($owner, self::WIDGET_ID);
        $placement = PlacementResolverRegistry::instance()->resolve_current($owner);
        self::assertNotNull($placement, 'The host page must resolve as the current placement.');
        $context = (new PaginationContextSigner())->sign($owner, self::WIDGET_ID, $definition->fingerprint(), $placement['type'], $placement['facts']);

        return [
            'version' => 1, 'owner_id' => $owner, 'widget_id' => self::WIDGET_ID,
            'page' => $page, 'fingerprint' => $definition->fingerprint(), 'context' => $context,
        ];
    }

    /** @return array{items: string, pagination: string, next_page: int} */
    private function endpointPage(int $owner, int $page): array
    {
        $response = (array) json_decode(NativeAjaxRequest::loop(wp_json_encode($this->payload($owner, $page)), ''), true);
        self::assertTrue($response['success'] ?? false, 'Page ' . $page . ' must succeed: ' . wp_json_encode($response));

        return $response['data'];
    }

    /** @return string[] Identities of the items in a rendered fragment. */
    private function itemIds(string $html): array
    {
        preg_match_all('/data-elemacy-item="([^"]+)"/', $html, $matches);

        return $matches[1];
    }
}
