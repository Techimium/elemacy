<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Tests\Integration\Fixtures\ArchitectureCatalog;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;
use Elementor\Plugin;
use ElemacyPro\Extensions\LoopDataSources\AcfRepeater\AcfRepeaterDataSource;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ArchitectureCatalog.php';
require_once __DIR__ . '/fixtures/RequestSimulator.php';

/**
 * Grid/Carousel's classic (non-AJAX) first-page output applies the same
 * public eligibility policy the AJAX endpoint enforces — an inaccessible
 * template/source must not appear there either (design.md D1) — while an
 * authorized editor preview remains unaffected.
 */
/**
 * Constructs ArchitectureCatalog, which requires ACF Pro's repeater
 * field type (task 9.6: excluded from the Free-only CI job that runs
 * without a licensed ACF Pro installation; run directly with
 * --acf-plugin for full local coverage).
 *
 * @group requires-acf-pro
 */
final class LoopInitialPageEligibilityTest extends IntegrationTestCase
{
    public function testInaccessibleTemplateProducesNoItemsForGuestsButRendersInEditorPreview(): void
    {
        $fixtures = new ArchitectureCatalog();
        $request = new RequestSimulator();

        try {
            $catalog = $fixtures->create();
            $request->visit(get_permalink($catalog['elementor_host']));

            $widget = Plugin::$instance->elements_manager->create_element_instance($this->raw_widget(
                $catalog['sources']['posts'] + ['template_id' => $catalog['library']['draft'], 'pagination_type' => '']
            ));

            $html = $this->render($widget);
            self::assertStringNotContainsString('elemacy-loop-item', $html, 'A guest must not see items rendered against a draft template: ' . $html);

            Plugin::instance()->editor->set_edit_mode(true);
            try {
                $html = $this->render($widget);
            } finally {
                Plugin::instance()->editor->set_edit_mode(false);
            }
            self::assertStringContainsString('elemacy-loop-item', $html, 'The author\'s own editor preview must still render their saved draft-template reference: ' . $html);
        } finally {
            $request->restore();
            $fixtures->cleanup();
        }
    }

    public function testInaccessibleAcfSourcePostProducesNoItemsForGuestsButRendersInEditorPreview(): void
    {
        $fixtures = new ArchitectureCatalog();
        $request = new RequestSimulator();
        $registry = LoopDataSourceRegistry::instance();
        $original_sources = $registry->all();

        try {
            $catalog = $fixtures->create();
            self::assertTrue(class_exists(AcfRepeaterDataSource::class), 'This test requires --with-pro.');
            // Not reliably self-registered in this isolated bootstrap (see
            // public-loop.php); register it directly, same as that suite.
            $registry->register(new AcfRepeaterDataSource());
            $request->visit(get_permalink($catalog['elementor_host']));

            // The fixture only seeds repeater rows on the published post/options
            // page; give the draft post its own rows so a real eligibility
            // rejection — not merely absent data — is what's under test.
            update_field($catalog['acf']['key'], $catalog['acf']['rows'], $catalog['posts']['draft']);

            $widget = Plugin::$instance->elements_manager->create_element_instance($this->raw_widget(array_merge(
                $catalog['sources']['acf_repeater'],
                ['specific_post_id' => $catalog['posts']['draft'], 'template_id' => $catalog['library']['published'], 'pagination_type' => '']
            )));

            $html = $this->render($widget);
            self::assertStringNotContainsString('elemacy-loop-item', $html, 'A guest must not see rows sourced from a draft post: ' . $html);

            Plugin::instance()->editor->set_edit_mode(true);
            try {
                $html = $this->render($widget);
            } finally {
                Plugin::instance()->editor->set_edit_mode(false);
            }
            self::assertStringContainsString('elemacy-loop-item', $html, 'The author\'s own editor preview must still render their saved draft-post reference: ' . $html);
        } finally {
            $property = new \ReflectionProperty($registry, 'sources');
            $property->setAccessible(true);
            $property->setValue($registry, $original_sources);
            $request->restore();
            $fixtures->cleanup();
        }
    }

    public function testAuthorizedPublicConfigurationStillRendersOnPageOne(): void
    {
        $fixtures = new ArchitectureCatalog();
        $request = new RequestSimulator();

        try {
            $catalog = $fixtures->create();
            $request->visit(get_permalink($catalog['elementor_host']));

            $widget = Plugin::$instance->elements_manager->create_element_instance($this->raw_widget(
                $catalog['sources']['posts'] + ['template_id' => $catalog['library']['published'], 'pagination_type' => '']
            ));

            $html = $this->render($widget);
            self::assertStringContainsString('elemacy-loop-item', $html, 'A valid, fully public configuration must still render for guests: ' . $html);
        } finally {
            $request->restore();
            $fixtures->cleanup();
        }
    }

    private function raw_widget(array $settings): array
    {
        return [
            'id' => 'eac9001', 'elType' => 'widget', 'widgetType' => 'elemacy-loop-grid',
            'settings' => $settings, 'elements' => [],
        ];
    }

    private function render($widget): string
    {
        $method = new \ReflectionMethod($widget, 'render');
        $method->setAccessible(true);
        ob_start();
        try {
            $method->invoke($widget);
        } finally {
            $html = ob_get_clean();
        }

        return $html;
    }
}
