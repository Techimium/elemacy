<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Tests\Integration\Fixtures\ArchitectureCatalog;
use Elemacy\Tests\Integration\Fixtures\ObservedLoopSource;
use ElemacyPro\Extensions\LoopDataSources\AcfRepeater\AcfRepeaterDataSource;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ArchitectureCatalog.php';
require_once __DIR__ . '/fixtures/ObservedLoopSource.php';

/** Stop WordPress's native JSON response without letting the router catch it. */
final class AjaxResponseFinished extends \Error
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
final class PublicLoopTest extends IntegrationTestCase
{
    /** @dataProvider substitutedRequests */
    public function testGuestCannotChooseQueryOrTemplateSettings(string $case): void
    {
        $fixtures = new ArchitectureCatalog();
        $registry = LoopDataSourceRegistry::instance();
        $original_sources = $registry->all();
        $original_post = $_POST;
        $original_get = $_GET;
        $original_user = get_current_user_id();
        $observers = [];
        $render_calls = 0;
        $rendered_documents = [];
        $response = null;
        $doing_ajax = static function (): bool { return true; };
        $stop = static function () {
            return static function (): void {
                throw new AjaxResponseFinished();
            };
        };
        $observe_render = static function ($data, $post_id) use (&$render_calls, &$rendered_documents) {
            ++$render_calls;
            $rendered_documents[] = (int) $post_id;
            return $data;
        };

        try {
            $catalog = $fixtures->create();
            // Exercise an available extension through its public registry hook,
            // without altering stored license state or faking WordPress/ACF.
            self::assertTrue(class_exists(AcfRepeaterDataSource::class), 'This paired suite requires --with-pro.');
            $sources = $original_sources + ['acf_repeater' => new AcfRepeaterDataSource()];
            foreach ($sources as $key => $source) {
                $observers[$key] = new ObservedLoopSource($source);
                // Deliberately substitutes the already-registered real source
                // with an instrumented wrapper for this test only: replace(),
                // not register() (task 10.1's RegistryIdentifierGuard treats
                // that as a duplicate-implementation conflict).
                $registry->replace($observers[$key]);
            }
            $settings = $catalog['sources']['posts'] + [
                'template_id' => $catalog['library']['published'], 'pagination_type' => 'numbers',
            ];
            if (isset($catalog['library'][$case])) {
                $settings['template_id'] = $catalog['library'][$case];
            } elseif ($case === 'post_type') {
                $settings['post_type'] = 'page';
            } elseif ($case === 'roles') {
                $settings = array_replace($settings, $catalog['sources']['users'], ['users_roles' => ['administrator']]);
            } elseif (strpos($case, 'acf_') === 0) {
                $settings = array_replace($settings, $catalog['sources']['acf_repeater']);
                if ($case === 'acf_options') {
                    $settings['source_type'] = 'options_page';
                    $settings['options_page_id'] = $catalog['acf']['options_id'];
                } elseif ($case === 'acf_private_post') {
                    update_field($catalog['acf']['key'], $catalog['acf']['rows'], $catalog['posts']['private']);
                    $settings['specific_post_id'] = $catalog['posts']['private'];
                } elseif ($case === 'acf_draft_post') {
                    update_field($catalog['acf']['key'], $catalog['acf']['rows'], $catalog['posts']['draft']);
                    $settings['specific_post_id'] = $catalog['posts']['draft'];
                }
            }

            wp_set_current_user(0);
            self::assertFalse(is_user_logged_in());
            $_GET = [];
            $_POST = wp_slash([
                'action' => 'elemacy_widgets_loop_grid_pagination',
                '_wpnonce' => wp_create_nonce('elemacy_ajax_nonce'),
                'settings' => wp_json_encode($settings),
                'paged' => '1',
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
            } catch (AjaxResponseFinished $finished) {
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
            $evidence = ['case' => $case, 'source_queries' => $query_calls,
                'elementor_renders' => $render_calls, 'rendered_documents' => $rendered_documents,
                'response_success' => $response['success'] ?? null];
            $environment = elemacy_test_environment((string) getenv('ELEMACY_TEST_ENV'));
            file_put_contents(dirname($environment['root']) . '/public-loop-evidence.jsonl', wp_json_encode($evidence) . "\n", FILE_APPEND | LOCK_EX);

            self::assertIsArray($response, 'The public AJAX action must return JSON.');
            self::assertSame(0, $query_calls, 'Unauthored settings reached a source: ' . wp_json_encode($evidence));
            self::assertSame(0, $render_calls, 'Unauthorized request reached Elementor.');
            self::assertFalse($response['success'], 'Legacy/substituted settings must require a page refresh.');
        } finally {
            remove_filter('wp_die_handler', $stop);
            remove_filter('wp_die_ajax_handler', $stop);
            remove_filter('wp_doing_ajax', $doing_ajax);
            remove_filter('elementor/frontend/builder_content_data', $observe_render, 10);
            // Registry currently has no unregister API; restore its exact prior
            // request-local state rather than leave the observation decorators.
            $property = new \ReflectionProperty($registry, 'sources');
            $property->setAccessible(true);
            $property->setValue($registry, $original_sources);
            $_POST = $original_post;
            $_GET = $original_get;
            wp_set_current_user($original_user);
            $fixtures->cleanup();
        }
    }

    public static function substitutedRequests(): array
    {
        return array_combine(
            ['legacy', 'draft', 'private', 'protected', 'wrong_type', 'wrong_group', 'post_type', 'roles', 'acf_field', 'acf_options', 'acf_private_post', 'acf_draft_post'],
            array_map(static function (string $case): array { return [$case]; },
                ['legacy', 'draft', 'private', 'protected', 'wrong_type', 'wrong_group', 'post_type', 'roles', 'acf_field', 'acf_options', 'acf_private_post', 'acf_draft_post'])
        );
    }
}
