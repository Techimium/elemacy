<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Core\Integrations\Elementor\SameDocumentContentRenderer;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * design.md D9/task 9.4: SameDocumentContentRenderer is PostContent's
 * exceptional same-post render-entry adapter, with finally-based editor-mode
 * and document-switch-stack restoration. PostContent's own branches (classic
 * fallback, password protection, recursion avoidance) are exercised at the
 * unit level in tests/php/run.php with a mocked frontend precise enough to
 * assert every one of them directly; what that fast harness's Elementor stub
 * cannot exercise is restoration of Elementor's *real*
 * Documents_Manager switch stack (its stub has no restore_document()) — this
 * file tests that directly against real Elementor, the same way
 * TemplateRenderingPreparationTest::testPrepareRestoresAmbientStateWhenRenderingThrows()
 * already does for TemplateRenderer's own equivalent restoration.
 */
final class SameDocumentContentRendererTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // See ForeignDocumentAssetsTest::setUp()/TemplateRenderingPreparationTest::setUp():
        // Elementor's Element Cache module defers dynamic-content elements
        // into an [elementor-element] shortcode unless disabled — irrelevant
        // to, and would otherwise mask, what this scenario tests.
        update_option('elementor_element_cache_ttl', 'disable');
    }

    protected function tearDown(): void
    {
        try {
            delete_option('elementor_element_cache_ttl');
        } finally {
            parent::tearDown();
        }
    }

    public function testRendersRealDesignContentForADifferentDocumentThanTheOneCurrentlyRendering(): void
    {
        $marker = 'Same-document renderer real content ' . wp_generate_uuid4();
        $target = $this->documents->create($marker);

        $html = (new SameDocumentContentRenderer())->render($target);

        self::assertStringContainsString($marker, $html, 'Real Elementor must render the saved heading.');
        self::assertStringContainsString('data-elementor-id="' . $target . '"', $html);
    }

    public function testRestoresElementorsRealDocumentStackAndEditModeAfterASuccessfulRender(): void
    {
        $target = $this->documents->create('Same-document renderer success restoration');

        $documents_manager = \Elementor\Plugin::instance()->documents;
        $editor = \Elementor\Plugin::instance()->editor;
        $document_before = $documents_manager->get_current();
        $edit_mode_before = $editor->is_edit_mode();

        (new SameDocumentContentRenderer())->render($target);

        self::assertSame($document_before, $documents_manager->get_current(), 'Elementor\'s current document must be restored after a successful render.');
        self::assertSame($edit_mode_before, $editor->is_edit_mode(), 'Elementor\'s edit-mode flag must be restored after a successful render.');
    }

    public function testRestoresElementorsRealDocumentStackAndEditModeWhenTheUnderlyingRenderThrows(): void
    {
        $target = $this->documents->create('Same-document renderer failure restoration', 'section', 'publish', [[
            'id' => 'eac9401', 'elType' => 'widget', 'widgetType' => 'elemacy-fixture-throws', 'settings' => [], 'elements' => [],
        ]]);

        self::assertTrue(class_exists('\Elementor\Plugin'), 'Elementor must be active for this scenario.');
        $documents_manager = \Elementor\Plugin::instance()->documents;
        $editor = \Elementor\Plugin::instance()->editor;

        $document_before = $documents_manager->get_current();
        $edit_mode_before = $editor->is_edit_mode();
        $ob_level_before = ob_get_level();

        $caught = null;

        try {
            (new SameDocumentContentRenderer())->render($target);
        } catch (\Throwable $error) {
            $caught = $error;
        }

        self::assertNotNull($caught, 'The widget fixture must actually throw for this scenario to be meaningful.');
        self::assertStringContainsString('elemacy-fixture-throws widget always throws', $caught->getMessage());

        self::assertSame($document_before, $documents_manager->get_current(), 'Elementor\'s current document must be restored after a failed render.');
        self::assertSame($edit_mode_before, $editor->is_edit_mode(), 'Elementor\'s edit-mode flag must be restored after a failed render.');
        self::assertSame($ob_level_before, ob_get_level(), 'No output buffer opened by the failed render may be left open.');
    }
}
