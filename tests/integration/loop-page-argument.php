<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Loop\Access\PaginationContextSigner;
use Elemacy\Loop\Access\PublicRenderPolicy;
use Elemacy\Loop\Access\PublishedLoopDefinitionResolver;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Pagination\PageArgument;
use Elemacy\Loop\Pagination\PageLinks;
use Elemacy\Loop\Placement\PlacementResolverRegistry;
use Elemacy\TemplateLibrary\TypeRegistry;
use Elemacy\Tests\Integration\Fixtures\NativeAjaxRequest;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;
use Elementor\Plugin;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/NativeAjaxRequest.php';
require_once __DIR__ . '/fixtures/RequestSimulator.php';

/**
 * A paginated Loop Grid with a non-Current-Query source follows Elementor
 * Pro's behaviour. On an archive or a singular page its links are the site's
 * paged URLs and WordPress is kept from answering 404 for a page the grid has
 * (but not for one past the grid's last page). One of several paginated
 * widgets in a singular page's document pages through its own
 * `elemacy-page-<widget id>` argument instead; the endpoint's links, for a
 * widget that loads pages in place, are always the classic render's.
 */
final class LoopPageArgumentTest extends IntegrationTestCase
{
    private const FIRST = 'eac9101';
    private const SECOND = 'eac9102';

    private RequestSimulator $request;
    private array $posts = [];
    private array $terms = [];
    private int $template = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new RequestSimulator();
        $suffix = bin2hex(random_bytes(4));
        $this->template = $this->documents->create('Page argument item ' . $suffix, 'loop');

        $term = wp_insert_term('Page argument term ' . $suffix, 'category');
        $this->terms[] = (int) $term['term_id'];

        foreach (range(1, 5) as $i) {
            $this->posts[] = (int) wp_insert_post([
                'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Page argument post ' . $i . ' ' . $suffix,
                'post_content' => 'Page argument fixture.', 'post_category' => $i <= 2 ? [$term['term_id']] : [],
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
        } finally {
            parent::tearDown();
        }
    }

    // ---- Archives: the site's /page/N/ URLs, kept from a 404 ----------------

    public function testAPagedArchiveWhoseMainQueryHasOnePageReachesTheGridsSecondPage(): void
    {
        $template = $this->archiveTemplate([self::FIRST => $this->postsGrid()]);

        $this->request->visit($this->archivePage(2));

        self::assertFalse(is_404(), 'The archive template has a second page of the grid, so the paged URL is found.');
        $html = $this->renderWidget($template, self::FIRST);
        self::assertSame($this->expectedIds(2), $this->itemIds($html), 'The grid shows its own page two.');
        self::assertStringNotContainsString('elemacy-page-', $html, 'An archive\'s grid pages through the site\'s paged URLs.');
        self::assertMatchesRegularExpression('#href="[^"]*(?:/page/3/|paged=3)[^"]*"#', $html);
    }

    public function testAPageBeyondTheGridsLastPageIsStillNotFound(): void
    {
        $this->archiveTemplate([self::FIRST => $this->postsGrid()]);

        $this->request->visit($this->archivePage(3));
        self::assertFalse(is_404(), 'The last page of the grid.');

        $this->request->visit($this->archivePage(4));
        self::assertTrue(is_404(), 'Past the last page of the grid, WordPress\'s own 404 stands.');
    }

    public function testThePageLimitEndsTheReach(): void
    {
        $this->archiveTemplate([self::FIRST => $this->postsGrid(['pagination_page_limit' => 2])]);

        $this->request->visit($this->archivePage(2));
        self::assertFalse(is_404());

        $this->request->visit($this->archivePage(3));
        self::assertTrue(is_404(), 'Page three is past the grid\'s Page Limit.');
    }

    public function testAGridWithoutPaginationMakesNoPagedUrlReal(): void
    {
        $this->archiveTemplate([self::FIRST => $this->postsGrid(['pagination_type' => ''])]);

        $this->request->visit($this->archivePage(2));

        self::assertTrue(is_404(), 'No pagination: nothing owns page two.');
    }

    public function testAGridThatLoadsInPlaceUsesTheSitesPagedUrlsToo(): void
    {
        $template = $this->archiveTemplate([self::FIRST => $this->postsGrid(['pagination_type' => 'load_more'])]);

        $this->request->visit($this->archivePage(2));

        self::assertFalse(is_404(), 'A shared or reloaded page-two URL of an in-place grid is found.');
        $html = $this->renderWidget($template, self::FIRST);
        self::assertSame($this->expectedIds(2), $this->itemIds($html));
        self::assertStringNotContainsString('elemacy-page-', $html);
        self::assertMatchesRegularExpression('#rel="next" href="[^"]*(?:/page/3/|paged=3)[^"]*"#', $html);
    }

    public function testAMissingTermStaysNotFoundWhateverTheTemplateHolds(): void
    {
        $this->archiveTemplate([self::FIRST => $this->postsGrid()]);

        $this->request->visit(add_query_arg('paged', 2, home_url('/?cat=999999')));

        self::assertTrue(is_404());
    }

    // ---- A singular page: /slug/N/ ------------------------------------------

    public function testASingularPagePagesItsGridThroughItsOwnPagedUrlWithoutA404(): void
    {
        $owner = $this->hostPage([self::FIRST => $this->postsGrid()]);
        $url = $this->singularUrl($owner, 2);

        $this->request->visit($url);

        self::assertFalse(is_404(), 'The page holds a second page of the grid.');
        $redirect = redirect_canonical($url, false);
        if (is_string($redirect)) {
            self::assertNotSame(untrailingslashit(get_permalink($owner)), untrailingslashit($redirect), 'No canonical redirect back to page one.');
        }
        $html = $this->renderWidget($owner, self::FIRST);
        self::assertSame($this->expectedIds(2), $this->itemIds($html));
        self::assertMatchesRegularExpression('#href="[^"]*(?:/3/|[?&](?:amp;|\#038;)?page=3)[^"]*"#', $html);
        self::assertStringNotContainsString('elemacy-page-', $html);

        $this->request->visit($this->singularUrl($owner, 4));
        self::assertTrue(is_404(), 'Past the grid\'s last page.');
    }

    public function testTheSinglePostTemplateOwnsItsGridsPagedUrlsToo(): void
    {
        $template = $this->singleTemplate([self::FIRST => $this->postsGrid()]);
        $post = $this->posts[0];

        $this->request->visit($this->singularUrl($post, 2));

        self::assertFalse(is_404());
        self::assertSame($this->expectedIds(2), $this->itemIds($this->renderWidget($template, self::FIRST)));
    }

    // ---- Several paginated widgets on one singular page: own arguments -------

    public function testTwoPaginatedGridsInOneSingularDocumentPageThroughTheirOwnArguments(): void
    {
        $owner = $this->hostPage([self::FIRST => $this->postsGrid(), self::SECOND => $this->postsGrid()]);
        $url = PageArgument::url(PageArgument::url(get_permalink($owner), self::FIRST, 2), self::SECOND, 3);

        $this->request->visit($url);
        $first = $this->renderWidget($owner, self::FIRST);
        $second = $this->renderWidget($owner, self::SECOND);

        self::assertFalse(is_404());
        self::assertSame($this->expectedIds(2), $this->itemIds($first));
        self::assertSame($this->expectedIds(3), $this->itemIds($second));
        self::assertSame(PageArgument::url($url, self::SECOND, 1), $this->pageLinks($second)['1'], 'A link of one grid keeps the other grid\'s page.');
        self::assertSame(PageArgument::url($url, self::FIRST, 1), $this->pageLinks($first)['1'], 'Page one removes only the grid\'s own argument.');

        foreach ($this->pageLinks($first) as $href) {
            self::assertStringNotContainsString('/page/', $href);
            self::assertStringNotContainsString('paged=', $href);
        }
    }

    public function testTheSitesPageDoesNotMoveAnArgumentWidgetAndItsPagedUrlIsNotFound(): void
    {
        $owner = $this->hostPage([self::FIRST => $this->postsGrid(), self::SECOND => $this->postsGrid()]);

        $this->request->visit($this->singularUrl($owner, 2));

        self::assertTrue(is_404(), 'The widgets\' links never lead to /slug/2/, so it stays a 404.');

        $this->request->visit(add_query_arg('paged', 2, get_permalink($owner)));
        self::assertSame($this->expectedIds(1), $this->itemIds($this->renderWidget($owner, self::FIRST)));
        self::assertSame($this->expectedIds(1), $this->itemIds($this->renderWidget($owner, self::SECOND)));
    }

    public function testAnythingButAPositiveWholeNumberIsPageOne(): void
    {
        $owner = $this->hostPage([self::FIRST => $this->postsGrid(), self::SECOND => $this->postsGrid()]);

        foreach (['abc', '0', '-3', '2.5'] as $value) {
            $this->request->visit(add_query_arg(PageArgument::name(self::FIRST), $value, get_permalink($owner)));
            self::assertSame($this->expectedIds(1), $this->itemIds($this->renderWidget($owner, self::FIRST)), 'Argument value ' . $value);
        }

        $this->request->visit(PageArgument::url(get_permalink($owner), self::SECOND, 2));
        self::assertSame($this->expectedIds(1), $this->itemIds($this->renderWidget($owner, self::FIRST)), 'Another widget\'s argument does not move this one.');
    }

    // ---- Paginated in place: the endpoint's links equal the classic links ---

    public function testTheEndpointsLinksAreTheSitesPagedUrlsOfASingularPage(): void
    {
        $owner = $this->hostPage([self::FIRST => $this->postsGrid(['pagination_ajax' => 'yes'])]);

        $this->request->visit($this->singularUrl($owner, 2));
        $html = $this->renderWidget($owner, self::FIRST);

        self::assertStringContainsString('data-elemacy-loop-singular="1"', $html);
        self::assertStringNotContainsString('data-elemacy-loop-page-arg', $html);
        self::assertSame(1, preg_match('/data-elemacy-loop-base="([^"]+)"/', $html, $match));
        $classic = $this->pageLinks($html);

        $this->request->visit(get_permalink($owner));
        $response = (array) json_decode(NativeAjaxRequest::loop(wp_json_encode($this->payload($owner, 2)), html_entity_decode($match[1])), true);
        self::assertTrue($response['success'] ?? false, wp_json_encode($response));
        self::assertSame($this->expectedIds(2), $this->itemIds($response['data']['items']));

        $endpoint = $this->pageLinks($response['data']['pagination']);
        self::assertSame($classic['3'], $endpoint['3'], 'The endpoint links the same page URL the classic render does.');
        self::assertSame($classic['Next »'], $endpoint['Next »']);
    }

    public function testAnInPlaceGridSharingASingularDocumentUsesItsOwnArgumentAndTheEndpointAgrees(): void
    {
        $owner = $this->hostPage([self::FIRST => $this->postsGrid(['pagination_ajax' => 'yes']), self::SECOND => $this->postsGrid()]);
        $base = add_query_arg('topic', 'news', get_permalink($owner));
        $url = PageArgument::url(PageArgument::url($base, self::FIRST, 2), self::SECOND, 3);

        $this->request->visit($url);
        $html = $this->renderWidget($owner, self::FIRST);

        self::assertSame($this->expectedIds(2), $this->itemIds($html));
        self::assertStringContainsString('data-elemacy-loop-page-arg="' . PageArgument::name(self::FIRST) . '"', $html);
        self::assertSame(1, preg_match('/data-elemacy-loop-base="([^"]+)"/', $html, $match));
        $classic = $this->pageLinks($html);
        self::assertSame(PageArgument::url($url, self::FIRST, 1), $classic['1']);

        $this->request->visit($url);
        $response = (array) json_decode(NativeAjaxRequest::loop(wp_json_encode($this->payload($owner, 2)), html_entity_decode($match[1])), true);
        self::assertTrue($response['success'] ?? false, wp_json_encode($response));
        self::assertSame($classic, $this->pageLinks($response['data']['pagination']), 'The endpoint\'s links equal the classic links of the same page, other widgets\' pages kept.');
    }

    public function testTheEndpointOnlyTakesAPaginationBaseOnThisSite(): void
    {
        $owner = $this->hostPage([self::FIRST => $this->postsGrid(['pagination_ajax' => 'yes'])]);
        $this->request->visit(get_permalink($owner));

        $response = (array) json_decode(NativeAjaxRequest::loop(wp_json_encode($this->payload($owner, 2)), 'https://elsewhere.example/page/%#%/'), true);

        self::assertTrue($response['success'] ?? false, wp_json_encode($response));
        self::assertStringNotContainsString('elsewhere.example', $response['data']['pagination']);
        self::assertMatchesRegularExpression('#(?:/page/3/|paged=3)#', $response['data']['pagination']);
    }

    // ---- Helpers -----------------------------------------------------------

    /** Posts three pages across every published post. */
    private function postsGrid(array $extra = []): array
    {
        return $extra + [
            'data_source' => 'posts', 'post_type' => 'post', 'posts_per_page' => $this->perPage(),
            'orderby' => 'date', 'order' => 'DESC', 'exclude_current_post' => '', 'offset' => 0,
            'pagination_type' => 'numbers_and_prev_next', 'template_id' => $this->template,
        ];
    }

    private function perPage(): int
    {
        return max(1, (int) ceil((int) wp_count_posts('post')->publish / 3));
    }

    /** @return string[] */
    private function expectedIds(int $page): array
    {
        return array_map('strval', get_posts([
            'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => $this->perPage(), 'paged' => $page,
            'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids', 'ignore_sticky_posts' => true,
        ]));
    }

    /** A page of the fixture category archive, whose own query has a single page. */
    private function archivePage(int $page): string
    {
        return add_query_arg('paged', $page, get_term_link($this->terms[0]));
    }

    /** The URL WordPress itself serves page $page of a post or page's content at. */
    private function singularUrl(int $post, int $page): string
    {
        $links = PageLinks::singular($post);

        return str_replace('%#%', (string) $page, str_replace('%_%', $links['format'], $links['base']));
    }

    /** @param array<string, array> $widgets Settings by widget id. */
    private function elements(array $widgets): array
    {
        $elements = [];
        foreach ($widgets as $id => $settings) {
            $elements[] = ['id' => $id, 'elType' => 'widget', 'widgetType' => 'elemacy-loop-grid', 'settings' => $settings, 'elements' => []];
        }

        return [['id' => 'eac9100', 'elType' => 'container', 'settings' => [], 'elements' => $elements]];
    }

    private function archiveTemplate(array $widgets): int
    {
        return $this->documents->create_everywhere('Page argument archive ' . bin2hex(random_bytes(3)), 'archive', 'publish', $this->elements($widgets));
    }

    private function singleTemplate(array $widgets): int
    {
        return $this->documents->create_everywhere('Page argument single ' . bin2hex(random_bytes(3)), 'single', 'publish', $this->elements($widgets));
    }

    /** A published page that holds the widgets itself. */
    private function hostPage(array $widgets): int
    {
        $page = Plugin::instance()->documents->create('wp-page', [
            'post_type' => 'page', 'post_title' => 'Page argument host ' . bin2hex(random_bytes(3)), 'post_status' => 'draft',
        ]);
        if (is_wp_error($page)) {
            throw new \RuntimeException($page->get_error_message());
        }
        $this->posts[] = $page->get_main_id();
        if (!$page->save(['elements' => $this->elements($widgets), 'settings' => ['post_status' => 'publish']])) {
            throw new \RuntimeException('Could not save the host page.');
        }

        return $page->get_main_id();
    }

    /** One widget's frontend render for the current simulated request. */
    private function renderWidget(int $owner, string $widget_id): string
    {
        $document = Plugin::$instance->documents->get_doc_for_frontend($owner);
        self::assertNotNull($document);
        Plugin::$instance->documents->switch_to_document($document);

        try {
            $raw = $this->findWidget($document->get_elements_data(), $widget_id);
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

    private function findWidget(array $elements, string $widget_id): ?array
    {
        foreach ($elements as $element) {
            if ($widget_id === ($element['id'] ?? null)) {
                return $element;
            }
            if (!empty($element['elements']) && null !== ($found = $this->findWidget($element['elements'], $widget_id))) {
                return $found;
            }
        }

        return null;
    }

    private function payload(int $owner, int $page): array
    {
        $policy = new PublicRenderPolicy(TypeRegistry::instance(), LoopDataSourceRegistry::instance());
        $definition = (new PublishedLoopDefinitionResolver($policy))->find($owner, self::FIRST);
        $placement = PlacementResolverRegistry::instance()->resolve_current($owner);
        self::assertNotNull($placement, 'The host page must resolve as the current placement.');
        $context = (new PaginationContextSigner())->sign($owner, self::FIRST, $definition->fingerprint(), $placement['type'], $placement['facts']);

        return [
            'version' => 1, 'owner_id' => $owner, 'widget_id' => self::FIRST,
            'page' => $page, 'fingerprint' => $definition->fingerprint(), 'context' => $context,
        ];
    }

    /** @return string[] Identities of the items in a rendered fragment. */
    private function itemIds(string $html): array
    {
        preg_match_all('/data-elemacy-item="([^"]+)"/', $html, $matches);

        return $matches[1];
    }

    /** @return array<string, string> Each pagination link's href, by its visible text. */
    private function pageLinks(string $html): array
    {
        preg_match_all('#<a class="[^"]*" href="([^"]*)">(.*?)</a>#s', $html, $matches, PREG_SET_ORDER);

        $links = [];
        foreach ($matches as $match) {
            $links[trim(html_entity_decode(wp_strip_all_tags(preg_replace('#<span class="[^"]*screen-reader-text">.*?</span>#', '', $match[2]))))] = html_entity_decode($match[1]);
        }

        return $links;
    }
}
