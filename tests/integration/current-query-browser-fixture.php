<?php

/**
 * Real WordPress/Elementor fixture for the browser journey of the Current
 * Query loop data source: a published Theme Builder Archive template holding
 * an AJAX-paginated Current Query Loop Grid, and a category of five posts at
 * two posts per page. Owns only the posts, term, documents and options it
 * records, even during a failed run.
 */
try {
    require __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/fixtures/LibraryDocument.php';

    $directory = dirname($elemacy_test_environment['root']);
    $command = $argv[1] ?? '';

    if ($command === 'create') {
        $administrator = get_user_by('login', 'elemacy_test_admin');
        wp_set_current_user($administrator->ID);
        // The environment manifest carries no admin password; the editor journey
        // needs one to sign in (the visitor journey ignores it).
        wp_set_password('pw-' . ELEMACY_INTEGRATION_TOKEN, $administrator->ID);

        // Pretty permalinks: the realistic configuration, and the one whose
        // page-2 URLs (/page/2/) the journey asserts on.
        $previous_structure = get_option('permalink_structure');
        $previous_per_page = get_option('posts_per_page');
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure('/%postname%/');
        // Not flush_rules(): taxonomies registered while permalinks were off have
        // no rewrite in this process, so the category rules would be missing. An
        // empty option makes the next real request regenerate them in full.
        delete_option('rewrite_rules');
        update_option('posts_per_page', 2);

        $documents = new Elemacy\Tests\Integration\Fixtures\LibraryDocument();
        $owned_posts = [];
        $owned_terms = [];

        try {
            $suffix = wp_generate_uuid4();
            $term = wp_insert_term('Browser current query ' . $suffix, 'category');
            elemacy_test_assert(!is_wp_error($term), 'Could not create the fixture category.');
            $owned_terms[] = (int) $term['term_id'];

            foreach (range(1, 5) as $i) {
                $id = wp_insert_post([
                    'post_type' => 'post', 'post_status' => 'publish',
                    'post_title' => 'Current query post ' . $i . ' ' . $suffix,
                    'post_content' => 'Current query fixture content ' . $i . '.',
                    'post_category' => [(int) $term['term_id']],
                    'post_date' => '2025-06-0' . $i . ' 10:00:00',
                    'post_date_gmt' => get_gmt_from_date('2025-06-0' . $i . ' 10:00:00'),
                    'meta_input' => ['_elemacy_fixture_environment' => ELEMACY_INTEGRATION_TOKEN],
                ], true);
                elemacy_test_assert(!is_wp_error($id), 'Could not create fixture post ' . $i . '.');
                $owned_posts[] = $id;
            }

            // The same item template the AJAX-pagination journey uses: a
            // heading plus a third-party widget that proves Elementor hydrated
            // the swapped-in items.
            $template = $documents->create('Current query loop item ' . $suffix, 'loop', 'publish', [[
                'id' => 'ecq2001', 'elType' => 'container', 'settings' => [],
                'elements' => [
                    ['id' => 'ecq2002', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => ['title' => 'Item ' . $suffix], 'elements' => []],
                    ['id' => 'ecq2003', 'elType' => 'widget', 'widgetType' => 'elemacy-fixture-thirdparty', 'settings' => [], 'elements' => []],
                ],
            ]]);
            $owned_posts[] = $template;

            $archive = $documents->create('Current query archive ' . $suffix, 'archive', 'publish', [[
                'id' => 'ecq1001', 'elType' => 'container', 'settings' => [],
                'elements' => [[
                    'id' => 'ecq1002', 'elType' => 'widget', 'widgetType' => 'elemacy-loop-grid',
                    'settings' => [
                        'data_source' => 'current_query', 'template_id' => $template,
                        'pagination_type' => 'numbers', 'pagination_ajax' => 'yes',
                    ],
                    'elements' => [],
                ]],
            ]]);
            $owned_posts[] = $archive;

            // The same loop in a Search Results template, for the search journey.
            $search = $documents->create('Current query search ' . $suffix, 'search', 'publish', [[
                'id' => 'ecq3001', 'elType' => 'container', 'settings' => [],
                'elements' => [[
                    'id' => 'ecq3002', 'elType' => 'widget', 'widgetType' => 'elemacy-loop-grid',
                    'settings' => [
                        'data_source' => 'current_query', 'template_id' => $template,
                        'pagination_type' => 'numbers', 'pagination_ajax' => 'yes',
                    ],
                    'elements' => [],
                ]],
            ]]);
            $owned_posts[] = $search;

            $fixture = [
                'token' => ELEMACY_INTEGRATION_TOKEN,
                // Built by hand: a taxonomy registered while permalinks were off keeps
                // its plain ?cat= link for the rest of this process, so get_term_link()
                // cannot give the pretty URL the browser will actually serve.
                'archive_url' => trailingslashit(home_url('/category/' . get_term((int) $term['term_id'], 'category')->slug)),
                'archive_id' => $archive,
                'search_url' => add_query_arg('s', rawurlencode($suffix), home_url('/')),
                'editor_url' => admin_url('post.php?post=' . $archive . '&action=elementor'),
                'post_ids' => array_slice($owned_posts, 0, 5), // the five posts, oldest first.
                'owned_posts' => $owned_posts,
                'owned_terms' => $owned_terms,
                'previous_permalink_structure' => $previous_structure,
                'previous_posts_per_page' => $previous_per_page,
                'manifest' => $directory . '/current-query-fixture-' . wp_generate_uuid4() . '.json',
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
    } elseif ($command === 'cleanup') {
        $path = realpath($argv[2] ?? '');
        elemacy_test_assert($path && dirname($path) === $directory
            && preg_match('/\Acurrent-query-fixture-[a-f0-9-]+\.json\z/', basename($path)), 'Refusing an unrelated fixture manifest.');
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
        foreach ($fixture['owned_terms'] ?? [] as $term_id) {
            wp_delete_term((int) $term_id, 'category');
        }
        global $wp_rewrite;
        update_option('posts_per_page', $fixture['previous_posts_per_page'] ?? 10);
        $wp_rewrite->set_permalink_structure((string) ($fixture['previous_permalink_structure'] ?? ''));
        delete_option('rewrite_rules');
        unlink($path);
        echo "Current Query browser fixture removed.\n";
    } else {
        throw new InvalidArgumentException('Use create or cleanup <fixture-manifest>.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Current Query browser fixture failed: ' . $error->getMessage() . "\n");
    exit(1);
}
