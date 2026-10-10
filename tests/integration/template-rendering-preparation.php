<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Elementor\Compatibility\Capabilities;
use Elemacy\Elementor\Rendering\TemplateAssetsRegistrar;
use Elemacy\Elementor\Rendering\TemplateRenderer;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * design.md D3/task 3.2: TemplateRenderer::prepare()/get_prepared() and
 * TemplateAssetsRegistrar::prepare()/get_prepared() are the primitive
 * request-owned preparation cache that later tasks (3.3-3.5) wire Popups and
 * Theme Builder onto. These tests exercise the primitive directly: shared
 * asset deduplication (a second prepare() for the same key never renders
 * again), full fragment retention (non-root sibling markup Elementor prints
 * alongside the `.elementor` root survives untouched), distinct
 * item-dependent markup per placement, and ambient state restoration when
 * rendering throws.
 */
final class TemplateRenderingPreparationTest extends IntegrationTestCase
{
    private array $posts = [];

    protected function setUp(): void
    {
        parent::setUp();

        // See ForeignDocumentAssetsTest::setUp(): Elementor's Element Cache
        // module replaces a widget's output with an [elementor-element]
        // shortcode placeholder unless it is expanded via do_shortcode() in
        // the same request that produced it — irrelevant to, and would
        // otherwise mask, what this scenario tests.
        update_option('elementor_element_cache_ttl', 'disable');
    }

    protected function tearDown(): void
    {
        try {
            foreach (array_reverse($this->posts) as $id) {
                wp_delete_post($id, true);
            }
        } finally {
            $this->posts = [];
            delete_option('elementor_element_cache_ttl');
            parent::tearDown();
        }
    }

    private function create_host(string $title): int
    {
        $id = wp_insert_post([
            'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $title,
        ], true);
        self::assertIsInt($id, 'Failed to create host post.');
        $this->posts[] = $id;

        return $id;
    }

    /** A popup document whose fragment carries the sibling-style/current-post marker. */
    private function create_marked_document(string $title): int
    {
        $id = $this->documents->create($title, 'popup', 'publish');
        update_post_meta($id, '_elemacy_fixture_item_marker', '1');

        return $id;
    }

    private function set_ambient_post(int $id): void
    {
        global $post, $wp_query;

        $post = get_post($id);
        self::assertNotNull($post, 'Ambient post fixture must exist.');
        setup_postdata($post);

        if (!($wp_query instanceof \WP_Query)) {
            $wp_query = new \WP_Query();
        }
        $wp_query->post = $post;
        $wp_query->posts = [$post];
        $wp_query->queried_object_id = $id;
    }

    public function testPrepareCachesFragmentAndDoesNotRenderAgainForTheSameKey(): void
    {
        $document = $this->create_marked_document('Prepare dedup');
        $hostA = $this->create_host('Ambient host A');
        $hostB = $this->create_host('Ambient host B');

        $renderer = new TemplateRenderer();

        $this->set_ambient_post($hostA);
        $first = $renderer->prepare($document, 'test', 'placement-1');
        self::assertStringContainsString(
            'data-elemacy-fixture-current-post>' . $hostA . '<',
            $first,
            'The prepared fragment must reflect the ambient post active during its one render.'
        );

        $this->set_ambient_post($hostB);
        $second = $renderer->prepare($document, 'test', 'placement-1');

        self::assertSame(
            $first,
            $second,
            'A second prepare() call for the same (id, context, placement) must return the cached fragment, not render again — the fragment still reflects host A even though the ambient post is now host B.'
        );
        self::assertStringNotContainsString('data-elemacy-fixture-current-post>' . $hostB . '<', $second);
    }

    public function testPrepareRetainsFullFragmentIncludingNonRootSiblingMarkup(): void
    {
        $document = $this->create_marked_document('Prepare fragment retention');
        $host = $this->create_host('Ambient host');
        $this->set_ambient_post($host);

        $renderer = new TemplateRenderer();
        $fragment = $renderer->prepare($document, 'test', 'placement-1');

        self::assertStringContainsString(
            '<style id="elemacy-fixture-sibling-style">',
            $fragment,
            'A sibling <style> fragment Elementor returns alongside the document root must be retained, not stripped down to only the .elementor element.'
        );
        self::assertMatchesRegularExpression(
            '#<div[^>]+class="elementor elementor-' . $document . '"#',
            $fragment,
            'The .elementor root itself must still be present.'
        );

        self::assertSame(
            $fragment,
            $renderer->get_prepared($document, 'test', 'placement-1'),
            'get_prepared() must return the exact fragment prepare() cached.'
        );
    }

    public function testPrepareIsolatesDistinctPlacementsOfTheSameTemplateByCurrentItem(): void
    {
        $document = $this->create_marked_document('Prepare item isolation');
        $itemA = $this->create_host('Loop item A');
        $itemB = $this->create_host('Loop item B');

        $renderer = new TemplateRenderer();

        $this->set_ambient_post($itemA);
        $fragmentA = $renderer->prepare($document, 'loop', 'occurrence-1');

        $this->set_ambient_post($itemB);
        $fragmentB = $renderer->prepare($document, 'loop', 'occurrence-2');

        self::assertNotSame($fragmentA, $fragmentB, 'Two placements of the same template with different current items must not share cached markup.');
        self::assertStringContainsString('data-elemacy-fixture-current-post>' . $itemA . '<', $fragmentA);
        self::assertStringContainsString('data-elemacy-fixture-current-post>' . $itemB . '<', $fragmentB);
        self::assertStringNotContainsString('data-elemacy-fixture-current-post>' . $itemB . '<', $fragmentA);
        self::assertStringNotContainsString('data-elemacy-fixture-current-post>' . $itemA . '<', $fragmentB);

        // Each placement's own cached fragment is independently retrievable,
        // regardless of which post is ambient right now.
        self::assertSame($fragmentA, $renderer->get_prepared($document, 'loop', 'occurrence-1'));
        self::assertSame($fragmentB, $renderer->get_prepared($document, 'loop', 'occurrence-2'));
    }

    public function testTwoDocumentsSharingADependencyBothPrepareSuccessfully(): void
    {
        $documentA = $this->documents->create('Shared dependency A', 'popup', 'publish', $this->widget_element('eac9201'));
        $documentB = $this->documents->create('Shared dependency B', 'popup', 'publish', $this->widget_element('eac9202'));
        $this->posts[] = $documentA;
        $this->posts[] = $documentB;

        $renderer = new TemplateRenderer();
        $fragmentA = $renderer->prepare($documentA, 'popup', (string) $documentA);
        $fragmentB = $renderer->prepare($documentB, 'popup', (string) $documentB);

        self::assertStringContainsString('data-elemacy-fixture-thirdparty', $fragmentA, 'Both documents must render the shared widget independently.');
        self::assertStringContainsString('data-elemacy-fixture-thirdparty', $fragmentB);
    }

    private function widget_element(string $id): array
    {
        return [[
            'id' => $id,
            'elType' => 'widget',
            'widgetType' => 'elemacy-fixture-thirdparty',
            'settings' => [],
            'elements' => [],
        ]];
    }

    public function testPrepareRestoresAmbientStateWhenRenderingThrows(): void
    {
        $document = $this->documents->create('Prepare error restoration', 'popup', 'publish', [[
            'id' => 'eac9301', 'elType' => 'widget', 'widgetType' => 'elemacy-fixture-throws', 'settings' => [], 'elements' => [],
        ]]);
        $this->posts[] = $document;

        $host = $this->create_host('Ambient host before failure');
        $this->set_ambient_post($host);

        self::assertTrue(class_exists('\Elementor\Plugin'), 'Elementor must be active for this scenario.');
        $documents_manager = \Elementor\Plugin::instance()->documents;
        $editor = \Elementor\Plugin::instance()->editor;

        $document_before = $documents_manager->get_current();
        $edit_mode_before = $editor->is_edit_mode();
        $ob_level_before = ob_get_level();

        $renderer = new TemplateRenderer();
        $caught = null;

        try {
            $renderer->prepare($document, 'test', 'error-1');
        } catch (\Throwable $error) {
            $caught = $error;
        }

        self::assertNotNull($caught, 'The widget fixture must actually throw for this scenario to be meaningful.');
        self::assertStringContainsString('elemacy-fixture-throws widget always throws', $caught->getMessage());

        self::assertSame($host, get_the_ID(), 'The ambient post must be restored to what it was before the failed render.');
        self::assertSame($document_before, $documents_manager->get_current(), 'Elementor\'s current document must be restored after a failed render.');
        self::assertSame($edit_mode_before, $editor->is_edit_mode(), 'Elementor\'s edit-mode flag must be restored after a failed render.');
        self::assertSame($ob_level_before, ob_get_level(), 'No output buffer opened by the failed render may remain open.');

        // The pipeline must remain usable for a later, unrelated render.
        $unrelated = $this->create_marked_document('Prepare after failure');
        $this->set_ambient_post($host);
        $fragment = $renderer->prepare($unrelated, 'test', 'after-failure');
        self::assertStringContainsString('data-elemacy-fixture-current-post>' . $host . '<', $fragment);
    }

    public function testRegistrarPrepareDelegatesToTheSharedRendererCacheAndBridgesCss(): void
    {
        $document = $this->create_marked_document('Registrar prepare delegation');
        $host = $this->create_host('Ambient host for registrar test');
        $this->set_ambient_post($host);

        $fired_for = [];
        add_action('elementor/post/render', static function ($id) use (&$fired_for): void {
            $fired_for[] = (int) $id;
        });

        $registrar = new TemplateAssetsRegistrar(new TemplateRenderer(), new Capabilities());
        $prepared = $registrar->prepare($document, 'test', 'registrar-1');

        self::assertContains($document, $fired_for, 'prepare() must still bridge classic/atomic CSS via register(), exactly as register() alone already does.');
        self::assertStringContainsString('data-elemacy-fixture-current-post>' . $host . '<', $prepared);

        // A fresh TemplateRenderer/TemplateAssetsRegistrar instance shares the
        // same request-scoped cache, so emission never needs to hold onto the
        // specific registrar instance that ran preparation.
        self::assertSame($prepared, (new TemplateAssetsRegistrar(new TemplateRenderer(), new Capabilities()))->get_prepared($document, 'test', 'registrar-1'));
        self::assertSame($prepared, (new TemplateRenderer())->get_prepared($document, 'test', 'registrar-1'));
    }
}
