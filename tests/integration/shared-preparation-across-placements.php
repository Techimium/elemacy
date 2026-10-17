<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Tests\Integration\Fixtures\ThemeLifecycleHarness;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ThemeLifecycleHarness.php';

/**
 * design.md D3/task 3.4: Theme Builder's header/footer/location rendering
 * consumes the same TemplateAssetsRegistrar/TemplateRenderer preparation
 * primitive (task 3.2) that Popups already consume (task 3.3). This
 * scenario exercises all three placements plus a matched popup at once, on
 * one block-theme host page, each carrying an instance of the same
 * third-party widget dependency — proving preparation across every
 * placement shares one asset delivery and never corrupts the ordinary
 * host page's own, separately and normally rendered content.
 */
final class SharedPreparationAcrossPlacementsTest extends IntegrationTestCase
{
    private ThemeLifecycleHarness $harness;
    private int $host_id;
    private array $owned = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->harness = new ThemeLifecycleHarness();
        if (!wp_get_theme('twentytwentyfive')->exists()) {
            self::markTestSkipped("Theme 'twentytwentyfive' is not available in this environment.");
        }

        // See ForeignDocumentAssetsTest::setUp(): the fixture widget does
        // not override is_dynamic_content(), so Elementor's Element Cache
        // module would otherwise replace it with an [elementor-element]
        // shortcode placeholder — irrelevant to what this scenario tests.
        update_option('elementor_element_cache_ttl', 'disable');

        $this->host_id = wp_insert_post([
            'post_type' => 'post', 'post_status' => 'publish',
            'post_title' => 'Ordinary host page', 'post_content' => 'Ordinary host page own content marker.',
        ], true);
        self::assertIsInt($this->host_id);

        $this->harness->switch_theme('twentytwentyfive');
    }

    protected function tearDown(): void
    {
        try {
            foreach (array_reverse($this->owned) as $id) {
                wp_delete_post($id, true);
            }
            wp_delete_post($this->host_id, true);
        } finally {
            delete_option('elementor_element_cache_ttl');
            $this->harness->restore();
            parent::tearDown();
        }
    }

    /** A heading marker plus the shared fixture third-party widget, as one document's elements. */
    private function marked_elements(string $marker, string $widget_id): array
    {
        return [
            [
                'id' => $widget_id . '1',
                'elType' => 'widget',
                'widgetType' => 'heading',
                'settings' => ['title' => $marker, 'header_size' => 'h2'],
                'elements' => [],
            ],
            [
                'id' => $widget_id . '2',
                'elType' => 'widget',
                'widgetType' => 'elemacy-fixture-thirdparty',
                'settings' => [],
                'elements' => [],
            ],
        ];
    }

    private function create_header(string $marker): int
    {
        $id = $this->documents->create($marker, 'header', 'publish', $this->marked_elements($marker, 'eac311'));
        $this->owned[] = $id;

        return $id;
    }

    private function create_footer(string $marker): int
    {
        $id = $this->documents->create($marker, 'footer', 'publish', $this->marked_elements($marker, 'eac312'));
        $this->owned[] = $id;

        return $id;
    }

    private function create_popup(string $marker): int
    {
        // A distinct post title, separate from the in-body heading marker:
        // PopupRenderer::build_markup() also uses get_the_title() as the
        // dialog's aria-label, which would otherwise duplicate the marker
        // text this test counts for the popup's own rendered body content.
        $id = $this->documents->create('Shared dependency popup fixture', 'popup', 'publish', $this->marked_elements($marker, 'eac313'));
        $this->owned[] = $id;

        return $id;
    }

    public function testHeaderFooterPopupAndOrdinaryHostShareOneWidgetDependencyWithOneDeliveryAndNoStateCorruption(): void
    {
        $this->create_header('Elemacy shared-dependency header');
        $this->create_footer('Elemacy shared-dependency footer');
        // A popup with no conditions matches every request (see
        // PopupDisplayResolver::resolve_all()) — no explicit rule needed.
        $this->create_popup('Elemacy shared-dependency popup');

        $html = $this->harness->render(get_permalink($this->host_id));

        // Each placement's own content rendered exactly once, uncorrupted
        // by the other placements preparing/rendering around it.
        self::assertSame(1, substr_count($html, 'Elemacy shared-dependency header'), 'Header content must render exactly once: ' . $html);
        self::assertSame(1, substr_count($html, 'Elemacy shared-dependency footer'), 'Footer content must render exactly once.');
        self::assertSame(1, substr_count($html, 'Elemacy shared-dependency popup'), 'Popup content must render exactly once (inside its <template>).');
        self::assertSame(1, substr_count($html, 'Ordinary host page own content marker'), 'The ordinary host page\'s own, normally-rendered content must be unaffected by header/footer/popup preparation.');

        // The shared widget actually rendered its own markup in all three
        // builder placements (host page itself does not use this widget).
        self::assertSame(3, substr_count($html, 'data-elemacy-fixture-thirdparty>'), 'The shared widget must render its own instance in the header, footer and popup.');

        // One delivery: despite three separate placements needing it, the
        // dependency's script/style is emitted exactly once each.
        self::assertSame(1, preg_match_all('#<script[^>]+elemacy-fixture-thirdparty[^>]*\.js#', $html), "The shared widget's script must be delivered exactly once for all three placements combined: " . $html);
        self::assertSame(1, preg_match_all('#<link[^>]+elemacy-fixture-thirdparty[^>]*\.css#', $html), "The shared widget's stylesheet must be delivered exactly once.");

        // One document shell — preparation never duplicates it.
        self::assertSame(1, preg_match_all('/<html[ >]/', $html));
        self::assertSame(1, substr_count($html, '<head>'));
        self::assertSame(1, substr_count($html, '</head>'));
        self::assertSame(1, substr_count($html, '<body'));
        self::assertSame(1, substr_count($html, '</body>'));
        self::assertSame(1, substr_count($html, '</html>'));
    }
}
