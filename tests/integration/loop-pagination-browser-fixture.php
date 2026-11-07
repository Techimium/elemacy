<?php

/**
 * Real WordPress/Elementor fixture for browser-driven AJAX loop pagination
 * (task 1.11): a flat AJAX-paginated Loop Grid, and a genuinely nested one
 * (an outer Loop Grid whose own item template contains another
 * AJAX-paginated Loop Grid). Owns only its own recorded posts, even during
 * a failed run.
 */
try {
    require __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/fixtures/LibraryDocument.php';

    $directory = dirname($elemacy_test_environment['root']);
    $command = $argv[1] ?? '';

    function elemacy_browser_loop_grid(array $settings): array
    {
        return [[
            'id' => 'ebl1001', 'elType' => 'container', 'settings' => [],
            'elements' => [[
                'id' => 'ebl1002', 'elType' => 'widget', 'widgetType' => 'elemacy-loop-grid',
                'settings' => $settings, 'elements' => [],
            ]],
        ]];
    }

    /**
     * A loop item template containing a heading (post title, unchanged from
     * before) plus the reusable elemacy-fixture-thirdparty widget (task 9.5)
     * — the same fixture run-popup-assets.mjs already uses to prove a real
     * `frontend/element_ready` handler actually runs, reused here to prove
     * the shared Elementor lifecycle adapter (assets/frontend/js/
     * elementor-lifecycle.js) re-initializes AJAX-paginated Loop Grid
     * content, not only popup content.
     */
    function elemacy_browser_loop_item(string $suffix): array
    {
        return [[
            'id' => 'ebl2001', 'elType' => 'container', 'settings' => [],
            'elements' => [
                ['id' => 'ebl2002', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => ['title' => 'Item ' . $suffix], 'elements' => []],
                ['id' => 'ebl2003', 'elType' => 'widget', 'widgetType' => 'elemacy-fixture-thirdparty', 'settings' => [], 'elements' => []],
            ],
        ]];
    }

    if ($command === 'create') {
        $administrator = get_user_by('login', 'elemacy_test_admin');
        wp_set_current_user($administrator->ID);

        // paginate_links() cannot build a valid page-2+ href for a
        // ?page_id=… URL under "Plain" permalinks (WP's own default for a
        // freshly provisioned site) without an explicit base — a
        // pre-existing characteristic of classic Loop Grid pagination
        // markup, unrelated to this change. Use pretty permalinks, the
        // realistic production configuration, so this fixture's pagination
        // links are real and clickable.
        $previous_structure = get_option('permalink_structure');
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure('/%postname%/');
        $wp_rewrite->flush_rules();

        $documents = new Elemacy\Tests\Integration\Fixtures\LibraryDocument();
        $owned_posts = [];

        try {
            $suffix = wp_generate_uuid4();
            foreach (range(1, 5) as $i) {
                $id = wp_insert_post([
                    'post_type' => 'post', 'post_status' => 'publish',
                    'post_title' => 'Browser pagination post ' . $i . ' ' . $suffix,
                    'post_content' => 'Browser pagination fixture content ' . $i . '.',
                    'meta_input' => ['_elemacy_fixture_environment' => ELEMACY_INTEGRATION_TOKEN],
                ], true);
                elemacy_test_assert(!is_wp_error($id), 'Could not create fixture post ' . $i . '.');
                $owned_posts[] = $id;
            }

            $inner_settings = [
                'data_source' => 'posts', 'post_type' => 'post', 'posts_per_page' => 2,
                'orderby' => 'title', 'order' => 'ASC', 'exclude_current_post' => 'no', 'offset' => 0,
                'pagination_type' => 'numbers', 'pagination_ajax' => 'yes',
            ];
            $inner_template = $documents->create('Inner loop item ' . $suffix, 'loop');
            $owned_posts[] = $inner_template;

            // Flat scenario: an ordinary page with one AJAX-paginated Loop Grid.
            $flat_template = $documents->create('Flat loop item ' . $suffix, 'loop', 'publish', elemacy_browser_loop_item($suffix));
            $owned_posts[] = $flat_template;
            $flat_page = Elementor\Plugin::instance()->documents->create('wp-page', [
                'post_type' => 'page', 'post_title' => 'Flat pagination host ' . $suffix, 'post_status' => 'draft',
            ]);
            elemacy_test_assert(!is_wp_error($flat_page), 'Could not create flat pagination host.');
            $flat_page_id = $flat_page->get_main_id();
            $owned_posts[] = $flat_page_id;
            update_post_meta($flat_page_id, '_elemacy_fixture_environment', ELEMACY_INTEGRATION_TOKEN);
            $flat_grid = elemacy_browser_loop_grid($inner_settings + ['template_id' => $flat_template]);
            elemacy_test_assert((bool) $flat_page->save(['elements' => $flat_grid, 'settings' => ['post_status' => 'publish']]), 'Could not save flat pagination host.');

            // Nested scenario: an outer Loop Grid (one classic item, no AJAX
            // of its own) whose item template contains another,
            // AJAX-paginated Loop Grid over the same post pool.
            $nested_inner_template = $documents->create('Nested inner loop item ' . $suffix, 'loop', 'publish', elemacy_browser_loop_item($suffix));
            $owned_posts[] = $nested_inner_template;
            $outer_template = $documents->create(
                'Outer loop item ' . $suffix,
                'loop',
                'publish',
                elemacy_browser_loop_grid($inner_settings + ['template_id' => $nested_inner_template])
            );
            $owned_posts[] = $outer_template;
            $nested_page = Elementor\Plugin::instance()->documents->create('wp-page', [
                'post_type' => 'page', 'post_title' => 'Nested pagination host ' . $suffix, 'post_status' => 'draft',
            ]);
            elemacy_test_assert(!is_wp_error($nested_page), 'Could not create nested pagination host.');
            $nested_page_id = $nested_page->get_main_id();
            $owned_posts[] = $nested_page_id;
            update_post_meta($nested_page_id, '_elemacy_fixture_environment', ELEMACY_INTEGRATION_TOKEN);
            $outer_grid = elemacy_browser_loop_grid([
                'data_source' => 'posts', 'post_type' => 'post', 'posts_per_page' => 1,
                'orderby' => 'title', 'order' => 'ASC', 'exclude_current_post' => 'no', 'offset' => 0,
                'pagination_type' => '', 'pagination_ajax' => 'no',
                'template_id' => $outer_template,
            ]);
            elemacy_test_assert((bool) $nested_page->save(['elements' => $outer_grid, 'settings' => ['post_status' => 'publish']]), 'Could not save nested pagination host.');

            $fixture = [
                'token' => ELEMACY_INTEGRATION_TOKEN,
                'flat_url' => get_permalink($flat_page_id),
                'nested_url' => get_permalink($nested_page_id),
                'owned_posts' => $owned_posts,
                'previous_permalink_structure' => $previous_structure,
                'manifest' => $directory . '/loop-pagination-fixture-' . wp_generate_uuid4() . '.json',
            ];
            elemacy_test_assert((bool) file_put_contents($fixture['manifest'], wp_json_encode($fixture)), 'Cannot record fixture ownership.');
            chmod($fixture['manifest'], 0600);
            echo wp_json_encode($fixture) . "\n";
        } catch (Throwable $error) {
            foreach (array_reverse($owned_posts) as $id) {
                wp_delete_post($id, true);
            }
            $documents->cleanup();
            $wp_rewrite->set_permalink_structure($previous_structure);
            $wp_rewrite->flush_rules();
            throw $error;
        }
    } elseif ($command === 'cleanup') {
        $path = realpath($argv[2] ?? '');
        elemacy_test_assert($path && dirname($path) === $directory
            && preg_match('/\Aloop-pagination-fixture-[a-f0-9-]+\.json\z/', basename($path)), 'Refusing an unrelated fixture manifest.');
        $fixture = json_decode(file_get_contents($path), true);
        elemacy_test_assert(($fixture['token'] ?? '') === ELEMACY_INTEGRATION_TOKEN, 'Fixture belongs to another environment.');
        foreach (array_reverse($fixture['owned_posts'] ?? []) as $id) {
            $id = (int) $id;
            if (!$id || !get_post($id)) {
                continue;
            }
            elemacy_test_assert(get_post_meta($id, '_elemacy_fixture_environment', true) === ELEMACY_INTEGRATION_TOKEN
                || get_post_type($id) === 'elemacy_library', 'Refusing to delete an unowned post.');
            wp_delete_post($id, true);
            elemacy_test_assert(!get_post($id), 'Fixture cleanup failed for post ' . $id . '.');
        }
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure((string) ($fixture['previous_permalink_structure'] ?? ''));
        $wp_rewrite->flush_rules();
        unlink($path);
        echo "Loop pagination browser fixture removed.\n";
    } else {
        throw new InvalidArgumentException('Use create or cleanup <fixture-manifest>.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Loop pagination browser fixture failed: ' . $error->getMessage() . "\n");
    exit(1);
}
