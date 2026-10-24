<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Tests\Integration\Fixtures\LibraryDocument;
use Elemacy\Tests\Integration\Fixtures\ThemeLifecycleHarness;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ThemeLifecycleHarness.php';

/**
 * design.md D3/task 3.1: a matched popup's own widget-specific script/style
 * dependencies must actually be delivered on a plain, non-Elementor host page
 * — not merely have their markup render. Elementor's own per-document asset
 * cache (`_elementor_page_assets`, computed at save time) already knows which
 * handles a document needs; the current gap is that PopupManager only ever
 * touches (renders) the document at `wp_footer` priority 99, after
 * `wp_print_footer_scripts` (WP core, default priority 10) has already
 * printed every script queued so far. A handle first enqueued that late is
 * queued for a request that will never print it.
 */
final class ForeignDocumentAssetsTest extends IntegrationTestCase
{
    private ThemeLifecycleHarness $harness;
    private int $host_id;
    private array $owned = [];

    protected function setUp(): void
    {
        parent::setUp();

        // A block theme (never touches a classic header.php/footer.php
        // file, so PHP's own require_once dedup across this long-lived
        // PHPUnit process can never collide) is used here purely as a
        // reliable generic host page — nothing in these tests exercises
        // theme-specific behavior. ThemeLifecycleHarness (not a raw
        // RequestSimulator) is used for the same reason task 2.4's own
        // fixtures need it: block-theme support/caches WordPress itself
        // only ever primes once per real process.
        $this->harness = new ThemeLifecycleHarness();
        if (!wp_get_theme('twentytwentyfive')->exists()) {
            self::markTestSkipped("Theme 'twentytwentyfive' is not available in this environment.");
        }
        $this->harness->switch_theme('twentytwentyfive');

        $this->host_id = wp_insert_post([
            'post_type' => 'post', 'post_status' => 'publish',
            'post_title' => 'Foreign document assets host', 'post_content' => 'Plain content, no Elementor.',
        ], true);
        self::assertIsInt($this->host_id);

        // Elementor's own "Element Cache" module (active by default —
        // elementor_element_cache_ttl defaults to '' at the option level,
        // which its own gate `'disable' !== get_option(...)` treats as
        // active) replaces a widget's output with an `[elementor-element]`
        // shortcode placeholder for any widget whose is_dynamic_content()
        // defaults true (Elemacy's own widgets never override it) unless
        // it is later expanded via do_shortcode() in the exact same request
        // context that produced it. That module's own caching lifecycle is
        // an orthogonal Elementor-core concern from what task 3.1 tests
        // here — the popup asset delivery timing — so it is disabled for
        // determinism, the same way a widget with an explicit
        // is_dynamic_content()=false (e.g. Heading) already sidesteps it in
        // every other fixture in this suite.
        update_option('elementor_element_cache_ttl', 'disable');
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

    private function create_popup(string $marker, array $elements): int
    {
        $id = $this->documents->create($marker, 'popup', 'publish', $elements);
        $this->owned[] = $id;

        return $id;
    }

    private function widget_element(string $id, string $widget_type, array $settings = []): array
    {
        return [[
            'id' => $id,
            'elType' => 'widget',
            'widgetType' => $widget_type,
            'settings' => $settings,
            'elements' => [],
        ]];
    }

    private function render_host(): string
    {
        return $this->harness->render(get_permalink($this->host_id));
    }

    public function testPopupOnlyFormScriptAndStyleAreDeliveredOnAPlainHostPage(): void
    {
        $this->create_popup('Popup with form', $this->widget_element('eac9101', 'elemacy-form'));

        $html = $this->render_host();

        self::assertStringContainsString('<form class="elemacy-form"', $html, 'The form widget itself must render: ' . $html);
        self::assertMatchesRegularExpression('#<script[^>]+elemacy-form[^>]*\.js#', $html, "The form's own script (needed for AJAX submission handling) must be delivered, not just its markup.");
        self::assertMatchesRegularExpression('#<link[^>]+elemacy-form[^>]*\.css#', $html, "The form's own stylesheet must be delivered.");
    }

    public function testPopupOnlyCarouselScriptAndStyleAreDeliveredOnAPlainHostPage(): void
    {
        $item = $this->documents->create('Carousel item template', 'loop', 'publish', LibraryDocument::heading('Carousel item'));
        $this->owned[] = $item;
        $source_post = wp_insert_post([
            'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Carousel source post',
        ], true);
        self::assertIsInt($source_post);
        $this->owned[] = $source_post;

        $this->create_popup('Popup with carousel', $this->widget_element('eac9102', 'elemacy-loop-carousel', [
            'template_id' => $item,
            'data_source' => 'posts',
        ]));

        $html = $this->render_host();

        self::assertStringContainsString('elemacy-loop-carousel-container', $html, 'The carousel widget itself must render: ' . $html);
        self::assertMatchesRegularExpression('#<script[^>]+elemacy-loop-carousel[^>]*\.js#', $html, "The carousel's own script (needed to initialize Swiper) must be delivered, not just its markup.");
        self::assertMatchesRegularExpression('#<link[^>]+elemacy-loop-carousel[^>]*\.css#', $html, "The carousel's own stylesheet must be delivered.");
    }

    public function testPopupOnlyThirdPartyAddonWidgetScriptAndStyleAreDeliveredOnAPlainHostPage(): void
    {
        $this->create_popup('Popup with third-party widget', $this->widget_element('eac9103', 'elemacy-fixture-thirdparty'));

        $html = $this->render_host();

        self::assertStringContainsString('data-elemacy-fixture-thirdparty', $html, 'The third-party widget itself must render: ' . $html);
        self::assertMatchesRegularExpression('#<script[^>]+elemacy-fixture-thirdparty[^>]*\.js#', $html, "A widget dependency owned by an entirely different addon (not Elemacy, not Elementor core) must be delivered too — the preparation mechanism must be generic, not special-cased to Elemacy's own widgets.");
        self::assertMatchesRegularExpression('#<link[^>]+elemacy-fixture-thirdparty[^>]*\.css#', $html, 'A third-party addon widget stylesheet must be delivered.');
    }

    public function testNoMatchedPopupPerformsNoPopupWorkOrAssetEnqueuing(): void
    {
        $html = $this->render_host();

        self::assertSame(0, substr_count($html, 'elemacy-popup'), 'No popup markup may appear when nothing matches: ' . $html);
        self::assertSame(0, substr_count($html, 'elemacy-popups-engine'), 'The popup engine script must not be enqueued when nothing matches.');
    }
}
