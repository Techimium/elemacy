<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Tests\Integration\Fixtures\ArchitectureCatalog;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;
use Elementor\Plugin;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/NativeAjaxRequest.php';
require_once __DIR__ . '/fixtures/ArchitectureCatalog.php';
require_once __DIR__ . '/fixtures/RequestSimulator.php';

/**
 * Constructs ArchitectureCatalog, which requires ACF Pro's repeater
 * field type (task 9.6: excluded from the Free-only CI job that runs
 * without a licensed ACF Pro installation; run directly with
 * --acf-plugin for full local coverage).
 *
 * @group requires-acf-pro
 */
final class LoopGridAjaxPayloadTest extends IntegrationTestCase
{
    /**
     * Builds a real Loop Grid widget instance from the fixture's own saved
     * element data (Elementor's own instantiation path — not a mock),
     * invokes its private ajax_request_attrs() in a real "visiting this
     * page" request context, and proves the emitted attribute is both
     * well-formed AND independently accepted by the real AJAX action.
     */
    public function testRenderedWidgetEmitsAVerifiableSignedRequestEnvelope(): void
    {
        $fixtures = new ArchitectureCatalog();
        $request = new RequestSimulator();

        try {
            $catalog = $fixtures->create();
            $owner_id = $catalog['elementor_host'];
            $widget_id = $catalog['widget_id'];

            $request->visit(get_permalink($owner_id));

            $document = Plugin::$instance->documents->get_doc_for_frontend($owner_id);
            self::assertNotNull($document, 'The fixture page must be a real Elementor document.');
            Plugin::$instance->documents->switch_to_document($document);

            $raw_widget = $this->find_widget($document->get_elements_data(), $widget_id);
            self::assertNotNull($raw_widget, 'Fixture widget not found in its own saved document.');
            $widget = Plugin::$instance->elements_manager->create_element_instance($raw_widget);
            self::assertNotNull($widget, 'Elementor could not instantiate the fixture Loop Grid widget.');

            $method = new \ReflectionMethod($widget, 'ajax_request_attrs');
            $method->setAccessible(true);
            $attrs = (string) $method->invoke($widget);

            Plugin::$instance->documents->restore_document();

            self::assertMatchesRegularExpression('/data-elemacy-loop-request="([^"]+)"/', $attrs, 'Emitted attributes: ' . $attrs);
            preg_match('/data-elemacy-loop-request="([^"]+)"/', $attrs, $matches);
            $payload = json_decode(html_entity_decode($matches[1]), true);

            self::assertIsArray($payload, 'The emitted payload must be valid JSON: ' . $matches[1]);
            self::assertSame($owner_id, $payload['owner_id']);
            self::assertSame($widget_id, $payload['widget_id']);
            self::assertSame(1, $payload['page']);
            self::assertNotEmpty($payload['fingerprint']);
            self::assertNotEmpty($payload['context'], 'A resolvable page placement must produce a signed context.');

            preg_match('/data-elemacy-loop-base="([^"]+)"/', $attrs, $base_match);
            self::assertNotEmpty($base_match[1] ?? '', 'Emitted attributes: ' . $attrs);

            self::assertResponseSucceedsForPayload($payload, html_entity_decode($base_match[1]));
        } finally {
            $request->restore();
            $fixtures->cleanup();
        }
    }

    /** @return array|null The raw element array with this id, searched recursively. */
    private function find_widget(array $elements, string $widget_id): ?array
    {
        foreach ($elements as $element) {
            if (($element['id'] ?? null) === $widget_id) {
                return $element;
            }
            if (!empty($element['elements'])) {
                $found = $this->find_widget($element['elements'], $widget_id);
                if (null !== $found) {
                    return $found;
                }
            }
        }

        return null;
    }

    private static function assertResponseSucceedsForPayload(array $payload, string $paginate_base): void
    {
        $original_post = $_POST;
        $original_get = $_GET;
        $original_user = get_current_user_id();

        try {
            wp_set_current_user(0);
            $_GET = [];
            $_POST = wp_slash([
                'action' => 'elemacy_widgets_loop_grid_pagination',
                '_wpnonce' => wp_create_nonce('elemacy_ajax_nonce'),
                'request' => wp_json_encode($payload),
                'paginate_base' => $paginate_base,
            ]);

            $body = \Elemacy\Tests\Integration\Fixtures\NativeAjaxRequest::loop(
                wp_unslash($_POST['request']), wp_unslash($_POST['paginate_base'])
            );

            $response = json_decode($body, true);
            self::assertTrue($response['success'] ?? false, 'The rendered widget\'s own emitted payload must be independently authorized: ' . $body);
        } finally {
            $_POST = $original_post;
            $_GET = $original_get;
            wp_set_current_user($original_user);
        }
    }
}
