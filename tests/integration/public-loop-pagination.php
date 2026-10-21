<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Access\PaginationContextSigner;
use Elemacy\Loop\Access\PublicRenderPolicy;
use Elemacy\Loop\Access\PublishedLoopDefinitionResolver;
use Elemacy\TemplateLibrary\TypeRegistry;
use Elemacy\Tests\Integration\Fixtures\ArchitectureCatalog;
use Elemacy\Tests\Integration\Fixtures\ObservedLoopSource;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/NativeAjaxRequest.php';
require_once __DIR__ . '/fixtures/ArchitectureCatalog.php';
require_once __DIR__ . '/fixtures/ObservedLoopSource.php';

/** Stop WordPress's native JSON response without letting the router catch it. */
final class NewProtocolAjaxResponseFinished extends \Error
{
}

/**
 * Constructs ArchitectureCatalog, which requires ACF Pro's repeater
 * field type (task 9.6: excluded from the Free-only CI job that runs
 * without a licensed ACF Pro installation; run directly with
 * --acf-plugin for full local coverage).
 *
 * @group requires-acf-pro
 */
final class PublicLoopPaginationTest extends IntegrationTestCase
{
    public function testValidNewProtocolRequestPaginatesSuccessfully(): void
    {
        $fixtures = new ArchitectureCatalog();
        $original_post = $_POST;
        $original_get = $_GET;
        $original_user = get_current_user_id();


        try {
            $catalog = $fixtures->create();

            $owner_id = $catalog['elementor_host'];
            $widget_id = $catalog['widget_id'];

            // Resolve the real, saved definition the same way the controller
            // will — this is how the browser would have obtained the
            // fingerprint on the original page load, not a shortcut around it.
            $policy = new PublicRenderPolicy(TypeRegistry::instance(), LoopDataSourceRegistry::instance());
            $definition = (new PublishedLoopDefinitionResolver($policy))->find($owner_id, $widget_id);
            $context = (new PaginationContextSigner())->sign(
                $owner_id,
                $widget_id,
                $definition->fingerprint(),
                'page',
                ['post_id' => $owner_id]
            );

            $payload = wp_json_encode([
                'version' => 1, 'owner_id' => $owner_id, 'widget_id' => $widget_id,
                'page' => 1, 'fingerprint' => $definition->fingerprint(), 'context' => $context,
            ]);

            wp_set_current_user(0);
            self::assertFalse(is_user_logged_in());
            $_GET = [];
            $_POST = wp_slash([
                'action' => 'elemacy_widgets_loop_grid_pagination',
                '_wpnonce' => wp_create_nonce('elemacy_ajax_nonce'),
                'request' => $payload,
                'paginate_base' => home_url('/page/%#%/'),
            ]);

            $body = \Elemacy\Tests\Integration\Fixtures\NativeAjaxRequest::loop(
                wp_unslash($_POST['request']), wp_unslash($_POST['paginate_base'])
            );

            $response = json_decode($body, true);
            self::assertIsArray($response, 'The public AJAX action must return JSON: ' . $body);
            self::assertTrue($response['success'] ?? false, 'A valid, authorized request must succeed: ' . $body);
            self::assertArrayHasKey('items', $response['data'], 'Response keys must be retained.');
            self::assertArrayHasKey('pagination', $response['data'], 'Response keys must be retained.');
            self::assertStringContainsString('elemacy-loop-item', $response['data']['items']);
        } finally {
            $_POST = $original_post;
            $_GET = $original_get;
            wp_set_current_user($original_user);
            $fixtures->cleanup();
        }
    }

    /**
     * Elementor stores nothing for a control left at its default, so page 2
     * (which replays the raw saved widget) must still continue exactly where
     * page 1 (which sees the defaults filled in) stopped.
     */
    public function testAWidgetSavedWithOnlyDefaultsContinuesOnPageTwoWithoutRepeatsOrGaps(): void
    {
        $fixtures = new ArchitectureCatalog();
        $request = new \Elemacy\Tests\Integration\Fixtures\RequestSimulator();
        $original_user = get_current_user_id();
        $posts = [];

        try {
            $catalog = $fixtures->create();
            // One minute apart, so the default date order has no ties.
            for ($i = 0; $i < 8; ++$i) {
                $posts[] = (int) wp_insert_post([
                    'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Defaults page ' . $i,
                    'post_date' => wp_date('Y-m-d H:i:s', time() - 3600 - $i * 60),
                ]);
            }
            $owner = \Elementor\Plugin::instance()->documents->create('wp-page', [
                'post_type' => 'page', 'post_title' => 'Defaults owner', 'post_status' => 'draft',
            ]);
            $owner_id = $owner->get_main_id();
            $posts[] = $owner_id;
            $saved = ['template_id' => $catalog['library']['published'], 'data_source' => 'posts', 'pagination_type' => 'ajax'];
            self::assertTrue((bool) $owner->save(['elements' => ArchitectureCatalog::grid($saved), 'settings' => ['post_status' => 'publish']]));

            $request->visit(get_permalink($owner_id));
            wp_set_current_user(0);
            $widget = \Elementor\Plugin::$instance->elements_manager->create_element_instance(ArchitectureCatalog::grid($saved)[0]['elements'][0]);
            $policy = new PublicRenderPolicy(TypeRegistry::instance(), LoopDataSourceRegistry::instance());
            $page_one = array_map(static function ($item): int {
                return (int) $item->get_identity();
            }, $policy->eligible_items($widget->get_settings_for_display(), $owner_id, 1)->items);

            $definition = (new PublishedLoopDefinitionResolver($policy))->find($owner_id, $catalog['widget_id']);
            $placement = \Elemacy\Loop\Placement\PlacementResolverRegistry::instance()->resolve_current($owner_id);
            $context = (new PaginationContextSigner())->sign($owner_id, $catalog['widget_id'], $definition->fingerprint(), $placement['type'], $placement['facts']);
            $body = \Elemacy\Tests\Integration\Fixtures\NativeAjaxRequest::loop((string) wp_json_encode([
                'version' => 1, 'owner_id' => $owner_id, 'widget_id' => $catalog['widget_id'],
                'page' => 2, 'fingerprint' => $definition->fingerprint(), 'context' => $context,
            ]));
            $response = json_decode($body, true);
            self::assertTrue($response['success'] ?? false, 'Page 2 must succeed: ' . $body);
            preg_match_all('/data-elemacy-item="(\d+)"/', $response['data']['items'], $matches);
            $page_two = array_map('intval', $matches[1]);

            // The Posts defaults: post type post, six per page, newest first.
            $expected = array_map('intval', get_posts([
                'post_type' => 'post', 'post_status' => 'publish', 'has_password' => false,
                'orderby' => 'date', 'order' => 'DESC', 'posts_per_page' => 12, 'fields' => 'ids',
            ]));
            self::assertSame(array_slice($expected, 0, 6), $page_one, 'Page 1 shows the six newest posts.');
            self::assertSame(array_slice($expected, 6, 6), $page_two, 'Page 2 is the next six, with no repeat or gap.');
        } finally {
            wp_set_current_user($original_user);
            $request->restore();
            foreach ($posts as $id) {
                wp_delete_post($id, true);
            }
            $fixtures->cleanup();
        }
    }

    /**
     * Task 1.7's own negative-authorization requirement: unlike an ordinary
     * page (owner_id publicly reachable on its own), a Loop Grid widget
     * authored inside a loop item template only exists because some
     * placement (here, nested_loop) resolved it for the current render — a
     * request that omits the signed placement token must be refused before
     * any source query or render, not silently treated as page-placement
     * with an empty context.
     */
    public function testLibraryOwnedDefinitionWithoutAPlacementTokenIsRefusedBeforeQueryOrRender(): void
    {
        $fixtures = new ArchitectureCatalog();
        $registry = LoopDataSourceRegistry::instance();
        $original_sources = $registry->all();
        $original_post = $_POST;
        $original_get = $_GET;
        $original_user = get_current_user_id();
        $observers = [];
        $render_calls = 0;
        $doing_ajax = static function (): bool { return true; };
        $stop = static function () {
            return static function (): void {
                throw new NewProtocolAjaxResponseFinished();
            };
        };
        $observe_render = static function ($data, $post_id) use (&$render_calls) {
            ++$render_calls;
            return $data;
        };

        try {
            $catalog = $fixtures->create();

            // The nested loop item template is itself an elemacy_library
            // document — its Loop Grid widget's owner is never an
            // independently reachable page the way $catalog['elementor_host']
            // is above.
            $owner_id = $catalog['nested_loop'];
            $widget_id = $catalog['widget_id'];

            foreach ($registry->all() as $key => $source) {
                $observers[$key] = new ObservedLoopSource($source);
                $registry->replace($observers[$key]);
            }

            $policy = new PublicRenderPolicy(TypeRegistry::instance(), $registry);
            $definition = (new PublishedLoopDefinitionResolver($policy))->find($owner_id, $widget_id);

            $payload = wp_json_encode([
                'version' => 1, 'owner_id' => $owner_id, 'widget_id' => $widget_id,
                'page' => 1, 'fingerprint' => $definition->fingerprint(), 'context' => '',
            ]);

            wp_set_current_user(0);
            self::assertFalse(is_user_logged_in());
            $_GET = [];
            $_POST = wp_slash([
                'action' => 'elemacy_widgets_loop_grid_pagination',
                '_wpnonce' => wp_create_nonce('elemacy_ajax_nonce'),
                'request' => $payload,
                'paginate_base' => '',
            ]);
            self::assertNotFalse(has_action('wp_ajax_nopriv_elemacy_widgets_loop_grid_pagination'));
            add_filter('wp_die_handler', $stop);
            add_filter('wp_die_ajax_handler', $stop);
            add_filter('wp_doing_ajax', $doing_ajax);
            add_filter('elementor/frontend/builder_content_data', $observe_render, 10, 2);
            $buffer_level = ob_get_level();
            ob_start();
            try {
                do_action('wp_ajax_nopriv_elemacy_widgets_loop_grid_pagination');
            } catch (NewProtocolAjaxResponseFinished $finished) {
                // Preserve the actual wp_send_json response for assertions.
            } finally {
                $body = '';
                while (ob_get_level() > $buffer_level) {
                    $body = ob_get_clean() . $body;
                }
            }

            $response = json_decode($body, true);
            $query_calls = array_sum(array_map(static function (ObservedLoopSource $source): int {
                return $source->query_calls;
            }, $observers));

            self::assertIsArray($response, 'The public AJAX action must return JSON: ' . $body);
            self::assertFalse($response['success'] ?? true, 'A library-owned definition without a signed placement token must be refused: ' . $body);
            self::assertSame('elemacy_loop_unavailable', $response['data']['errors']['code'] ?? null, wp_json_encode($response));
            self::assertSame(0, $query_calls, 'An unauthorized library-owned request reached a source: ' . wp_json_encode($response));
            self::assertSame(0, $render_calls, 'An unauthorized library-owned request reached Elementor.');
        } finally {
            remove_filter('wp_die_handler', $stop);
            remove_filter('wp_die_ajax_handler', $stop);
            remove_filter('wp_doing_ajax', $doing_ajax);
            remove_filter('elementor/frontend/builder_content_data', $observe_render, 10);
            $property = new \ReflectionProperty($registry, 'sources');
            $property->setAccessible(true);
            $property->setValue($registry, $original_sources);
            $_POST = $original_post;
            $_GET = $original_get;
            wp_set_current_user($original_user);
            $fixtures->cleanup();
        }
    }
}
