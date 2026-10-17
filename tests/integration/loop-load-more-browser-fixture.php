<?php

/**
 * Real WordPress/Elementor fixture for the Load More / Infinite Scroll browser
 * journey (tests/e2e/run-loop-load-more.mjs). Creates, at two items per page:
 *
 *  - three pages with Posts grids: Load More, Infinite Scroll, and two
 *    independent Load More grids;
 *  - a Theme Builder Archive template whose Current Query grid is Load More,
 *    and a Search Results template whose Current Query grid is Infinite Scroll.
 *
 * One column of tall items, so Infinite Scroll only loads once the visitor scrolls.
 * `shift` publishes one newer post, moving every later page by one item.
 * Owns only what it records, even during a failed run.
 */
try {
    require __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/fixtures/LibraryDocument.php';

    $directory = dirname($elemacy_test_environment['root']);
    $command = $argv[1] ?? '';

    function elemacy_lm_grid(string $widget_id, array $settings): array
    {
        return [[
            'id' => 'elm' . substr($widget_id, 3) . '0', 'elType' => 'container', 'settings' => [],
            'elements' => [[
                'id' => $widget_id, 'elType' => 'widget', 'widgetType' => 'elemacy-loop-grid',
                'settings' => $settings, 'elements' => [],
            ]],
        ]];
    }

    function elemacy_lm_manifest(string $directory, string $path): array
    {
        $real = realpath($path);
        elemacy_test_assert($real && dirname($real) === $directory
            && preg_match('/\Aloop-load-more-fixture-[a-f0-9-]+\.json\z/', basename($real)), 'Refusing an unrelated fixture manifest.');
        $fixture = json_decode(file_get_contents($real), true);
        elemacy_test_assert(($fixture['token'] ?? '') === ELEMACY_INTEGRATION_TOKEN, 'Fixture belongs to another environment.');

        return [$real, $fixture];
    }

    function elemacy_lm_post_ids(): array
    {
        return array_map('intval', get_posts([
            'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => -1,
            'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids', 'ignore_sticky_posts' => true,
        ]));
    }

    if ($command === 'create') {
        $administrator = get_user_by('login', 'elemacy_test_admin');
        wp_set_current_user($administrator->ID);
        // The environment manifest carries no admin password; the editor journey needs one to sign in.
        wp_set_password('pw-' . ELEMACY_INTEGRATION_TOKEN, $administrator->ID);

        $previous_structure = get_option('permalink_structure');
        $previous_per_page = get_option('posts_per_page');
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure('/%postname%/');
        // An empty option makes the next real request regenerate every rule,
        // category base included (see current-query-browser-fixture.php).
        delete_option('rewrite_rules');
        update_option('posts_per_page', 2);

        $documents = new Elemacy\Tests\Integration\Fixtures\LibraryDocument();
        $owned_posts = [];
        $owned_terms = [];

        try {
            $suffix = wp_generate_uuid4();
            $term = wp_insert_term('Load more ' . $suffix, 'category');
            elemacy_test_assert(!is_wp_error($term), 'Could not create the fixture category.');
            $owned_terms[] = (int) $term['term_id'];

            foreach (range(1, 5) as $i) {
                $date = gmdate('Y-m-d H:i:s', time() - 3600 - $i * 60);
                $id = wp_insert_post([
                    'post_type' => 'post', 'post_status' => 'publish',
                    'post_title' => 'Load more post ' . $i . ' ' . $suffix,
                    'post_content' => 'Load more fixture content ' . $i . '.',
                    'post_category' => [(int) $term['term_id']],
                    'post_date_gmt' => $date, 'post_date' => get_date_from_gmt($date),
                    'meta_input' => ['_elemacy_fixture_environment' => ELEMACY_INTEGRATION_TOKEN],
                ], true);
                elemacy_test_assert(!is_wp_error($id), 'Could not create fixture post ' . $i . '.');
                $owned_posts[] = $id;
            }

            // A tall item (so scrolling is needed to reach the end of a loop)
            // plus a widget that proves Elementor hydrated appended items.
            $template = $documents->create('Load more item ' . $suffix, 'loop', 'publish', [[
                'id' => 'elm2001', 'elType' => 'container', 'settings' => [],
                'elements' => [
                    ['id' => 'elm2002', 'elType' => 'widget', 'widgetType' => 'html', 'settings' => ['html' => '<div style="height:700px"></div>'], 'elements' => []],
                    ['id' => 'elm2003', 'elType' => 'widget', 'widgetType' => 'elemacy-fixture-thirdparty', 'settings' => [], 'elements' => []],
                ],
            ]]);
            $owned_posts[] = $template;

            $posts_grid = static fn (string $type, int $per_page = 2): array => [
                'data_source' => 'posts', 'post_type' => 'post', 'posts_per_page' => $per_page,
                'orderby' => 'date', 'order' => 'DESC', 'exclude_current_post' => '', 'offset' => 0,
                'template_id' => $template, 'pagination_type' => $type, 'load_more_end_text' => "That's all", 'columns' => '1',
            ];
            $current_query_grid = static fn (string $type): array => [
                'data_source' => 'current_query', 'template_id' => $template,
                'pagination_type' => $type, 'load_more_end_text' => "That's all", 'columns' => '1',
            ];

            $page = static function (string $title, array $elements) use (&$owned_posts): int {
                $document = Elementor\Plugin::instance()->documents->create('wp-page', [
                    'post_type' => 'page', 'post_title' => $title . ' ' . bin2hex(random_bytes(3)), 'post_status' => 'draft', // not the suffix: the search journey must match only the five posts.
                ]);
                elemacy_test_assert(!is_wp_error($document), 'Could not create ' . $title . '.');
                $id = $document->get_main_id();
                $owned_posts[] = $id;
                update_post_meta($id, '_elemacy_fixture_environment', ELEMACY_INTEGRATION_TOKEN);
                elemacy_test_assert((bool) $document->save(['elements' => $elements, 'settings' => ['post_status' => 'publish']]), 'Could not save ' . $title . '.');

                return $id;
            };

            $load_more_page = $page('Load more host', elemacy_lm_grid('elm1001', $posts_grid('load_more')));
            $infinite_page = $page('Infinite host', elemacy_lm_grid('elm1002', $posts_grid('infinite_scroll')));
            $two_loops_page = $page('Two loops host', array_merge(
                elemacy_lm_grid('elm1003', $posts_grid('load_more', 2)),
                elemacy_lm_grid('elm1004', $posts_grid('load_more', 3))
            ));

            $archive = $documents->create('Load more archive ' . $suffix, 'archive', 'publish', elemacy_lm_grid('elm1005', $current_query_grid('load_more')));
            $owned_posts[] = $archive;
            $owned_posts[] = $documents->create('Load more search ' . $suffix, 'search', 'publish', elemacy_lm_grid('elm1006', $current_query_grid('infinite_scroll')));

            $fixture = [
                'token' => ELEMACY_INTEGRATION_TOKEN,
                'load_more_url' => get_permalink($load_more_page),
                'infinite_url' => get_permalink($infinite_page),
                'two_loops_url' => get_permalink($two_loops_page),
                'editor_url' => admin_url('post.php?post=' . $archive . '&action=elementor'),
                'archive_url' => trailingslashit(home_url('/category/' . get_term((int) $term['term_id'], 'category')->slug)),
                'search_url' => add_query_arg('s', rawurlencode($suffix), home_url('/')),
                'post_ids' => elemacy_lm_post_ids(),
                'category_post_ids' => array_slice($owned_posts, 0, 5), // newest first: their dates descend in creation order.
                'owned_posts' => $owned_posts,
                'owned_terms' => $owned_terms,
                'previous_permalink_structure' => $previous_structure,
                'previous_posts_per_page' => $previous_per_page,
                'manifest' => $directory . '/loop-load-more-fixture-' . wp_generate_uuid4() . '.json',
            ];
            elemacy_test_assert((bool) file_put_contents($fixture['manifest'], wp_json_encode($fixture)), 'Cannot record fixture ownership.');
            chmod($fixture['manifest'], 0600);
            echo wp_json_encode($fixture) . "\n";
        } catch (Throwable $error) {
            foreach (array_reverse($owned_posts) as $id) {
                wp_delete_post($id, true);
            }
            foreach ($owned_terms as $term_id) {
                wp_delete_term($term_id, 'category');
            }
            $documents->cleanup();
            update_option('posts_per_page', $previous_per_page);
            $wp_rewrite->set_permalink_structure($previous_structure);
            delete_option('rewrite_rules');
            throw $error;
        }
    } elseif ($command === 'shift') {
        // One newer post: every later page now starts one item earlier.
        [$path, $fixture] = elemacy_lm_manifest($directory, $argv[2] ?? '');
        $id = wp_insert_post([
            'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Load more shift ' . wp_generate_uuid4(),
            'post_content' => 'Shifts every later page by one.',
            'meta_input' => ['_elemacy_fixture_environment' => ELEMACY_INTEGRATION_TOKEN],
        ], true);
        elemacy_test_assert(!is_wp_error($id), 'Could not publish the shifting post.');
        $fixture['owned_posts'][] = $id;
        file_put_contents($path, wp_json_encode($fixture));
        echo wp_json_encode(['post_ids' => elemacy_lm_post_ids()]) . "\n";
    } elseif ($command === 'cleanup') {
        [$path, $fixture] = elemacy_lm_manifest($directory, $argv[2] ?? '');
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
        foreach ($fixture['owned_terms'] ?? [] as $term_id) {
            wp_delete_term((int) $term_id, 'category');
        }
        global $wp_rewrite;
        update_option('posts_per_page', $fixture['previous_posts_per_page'] ?? 10);
        $wp_rewrite->set_permalink_structure((string) ($fixture['previous_permalink_structure'] ?? ''));
        delete_option('rewrite_rules');
        unlink($path);
        echo "Load more browser fixture removed.\n";
    } else {
        throw new InvalidArgumentException('Use create, shift <manifest> or cleanup <manifest>.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Load more browser fixture failed: ' . $error->getMessage() . "\n");
    exit(1);
}
