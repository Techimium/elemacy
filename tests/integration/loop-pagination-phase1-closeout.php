<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Modules\Widgets\Services\LoopDataSourceRegistry;
use Elemacy\Modules\Widgets\Services\LoopTemplateRenderStack;
use Elemacy\Modules\Widgets\Services\PublicRenderPolicy;
use Elemacy\Modules\Widgets\Services\PublishedLoopDefinitionResolver;
use Elemacy\TemplateLibrary\TypeRegistry;
use Elemacy\Tests\Integration\Fixtures\ArchitectureCatalog;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;
use Elementor\Plugin;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/NativeAjaxRequest.php';
require_once __DIR__ . '/fixtures/ArchitectureCatalog.php';
require_once __DIR__ . '/fixtures/RequestSimulator.php';

/**
 * Phase-1 close-out (task 1.11): cross-checks classic vs. AJAX output for
 * the same public configuration, and exercises the nested-loop and
 * cached-context scenarios D1 specifically calls out, end to end through
 * the real AJAX action. See docs/RELEASE-VALIDATION.md for what this suite
 * covers versus the browser cases this environment cannot execute.
 */
/**
 * Constructs ArchitectureCatalog, which requires ACF Pro's repeater
 * field type (task 9.6: excluded from the Free-only CI job that runs
 * without a licensed ACF Pro installation; run directly with
 * --acf-plugin for full local coverage).
 *
 * @group requires-acf-pro
 */
final class LoopPaginationPhase1CloseoutTest extends IntegrationTestCase
{
    public function testClassicAndAjaxPaginationAgreeForThePostsSource(): void
    {
        $fixtures = new ArchitectureCatalog();
        $request = new RequestSimulator();

        try {
            $catalog = $fixtures->create();
            $request->visit(get_permalink($catalog['elementor_host']));

            $settings = $catalog['sources']['posts'] + ['template_id' => $catalog['library']['published']];
            $policy = new PublicRenderPolicy(TypeRegistry::instance(), LoopDataSourceRegistry::instance());
            $classic = $policy->eligible_items($settings, $catalog['elementor_host']);

            $ajax_ids = $this->paginate($catalog['elementor_host'], $catalog['widget_id'], 1);

            $classic_ids = array_map(static fn ($item) => (string) $item->get_field('ID'), $classic->items);
            self::assertNotEmpty($classic_ids);
            self::assertSame($classic_ids, $ajax_ids, 'Classic and AJAX pagination must select and order the same items under the same policy.');
        } finally {
            $request->restore();
            $fixtures->cleanup();
        }
    }

    public function testNestedLoopGridPaginatesThroughTheRealAjaxAction(): void
    {
        $fixtures = new ArchitectureCatalog();
        $request = new RequestSimulator();

        try {
            $catalog = $fixtures->create();
            $request->visit(get_permalink($catalog['elementor_host']));

            $document = Plugin::$instance->documents->get_doc_for_frontend($catalog['nested_loop']);
            self::assertNotNull($document);
            Plugin::$instance->documents->switch_to_document($document);
            $raw = $this->find_widget($document->get_elements_data(), $catalog['widget_id']);
            self::assertNotNull($raw, 'Nested fixture widget not found.');
            $widget = Plugin::$instance->elements_manager->create_element_instance($raw);

            // The nested widget's owner is the loop template actively
            // rendering it — simulate exactly what LoopGrid::render()'s own
            // per-item loop does around get_builder_content_for_display().
            LoopTemplateRenderStack::push($catalog['nested_loop']);
            try {
                $attrs = $this->invoke_ajax_request_attrs($widget);
            } finally {
                LoopTemplateRenderStack::pop();
                Plugin::$instance->documents->restore_document();
            }

            preg_match('/data-elemacy-loop-request="([^"]+)"/', $attrs, $matches);
            self::assertNotEmpty($matches[1] ?? '', 'Nested widget did not emit a request envelope: ' . $attrs);
            $payload = json_decode(html_entity_decode($matches[1]), true);
            self::assertSame('nested_loop', $this->decode_context_type($payload['context']));

            $response = $this->submit($payload, '');
            self::assertTrue($response['success'] ?? false, 'A nested Loop Grid\'s own signed payload must be independently authorized: ' . wp_json_encode($response));
        } finally {
            $request->restore();
            $fixtures->cleanup();
        }
    }

    public function testASignedContextRemainsValidAfterALaterUnrelatedRequest(): void
    {
        $fixtures = new ArchitectureCatalog();
        $request = new RequestSimulator();

        try {
            $catalog = $fixtures->create();
            $request->visit(get_permalink($catalog['elementor_host']));

            $document = Plugin::$instance->documents->get_doc_for_frontend($catalog['elementor_host']);
            Plugin::$instance->documents->switch_to_document($document);
            $raw = $this->find_widget($document->get_elements_data(), $catalog['widget_id']);
            $widget = Plugin::$instance->elements_manager->create_element_instance($raw);
            $attrs = $this->invoke_ajax_request_attrs($widget);
            Plugin::$instance->documents->restore_document();

            preg_match('/data-elemacy-loop-request="([^"]+)"/', $attrs, $matches);
            $cached_payload = json_decode(html_entity_decode($matches[1]), true);

            // Simulate a page cached earlier being paginated well after that
            // render — an unrelated request happens on this same process in
            // between (design.md D1: tokens are not one-use secrets).
            $request->visit(get_permalink($catalog['posts']['published']));

            $response = $this->submit($cached_payload, '');
            self::assertTrue($response['success'] ?? false, 'A still-valid cached signed context must remain replayable: ' . wp_json_encode($response));
        } finally {
            $request->restore();
            $fixtures->cleanup();
        }
    }

    /** @return string[] Post IDs in the exact order the AJAX response rendered them. */
    private function paginate(int $owner_id, string $widget_id, int $page): array
    {
        $policy = new PublicRenderPolicy(TypeRegistry::instance(), LoopDataSourceRegistry::instance());
        $definition = (new PublishedLoopDefinitionResolver($policy))->find($owner_id, $widget_id);
        $payload = [
            'version' => 1, 'owner_id' => $owner_id, 'widget_id' => $widget_id,
            'page' => $page, 'fingerprint' => $definition->fingerprint(), 'context' => '',
        ];
        $response = $this->submit($payload, '');
        self::assertTrue($response['success'] ?? false, 'AJAX pagination must succeed: ' . wp_json_encode($response));

        preg_match_all('/elemacy-loop-item-([A-Za-z0-9_-]+)/', $response['data']['items'], $item_matches);

        // Task 8.7: the rendered class is now LoopRenderIdentity's own
        // render-scoped selector (raw item identity + a 12-hex-char scope
        // hash), not the bare domain identity — strip that known-length
        // suffix back off to recover the post ID this test actually
        // compares against the classic-render side.
        return array_map(static function (string $selector): string {
            return preg_replace('/-[0-9a-f]{12}$/', '', $selector);
        }, $item_matches[1]);
    }

    private function submit(array $payload, string $paginate_base): array
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

            return (array) json_decode($body, true);
        } finally {
            $_POST = $original_post;
            $_GET = $original_get;
            wp_set_current_user($original_user);
        }
    }

    private function invoke_ajax_request_attrs($widget): string
    {
        $method = new \ReflectionMethod($widget, 'ajax_request_attrs');
        $method->setAccessible(true);

        return (string) $method->invoke($widget);
    }

    private function decode_context_type(string $context): ?string
    {
        [$encoded] = explode('.', $context, 2);
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
        $claims = json_decode((string) $decoded, true);

        return $claims['type'] ?? null;
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
}
