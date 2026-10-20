<?php

/**
 * Real WordPress/Elementor fixture for browser-driven popup asset delivery
 * (task 3.1): a plain, non-Elementor host page matches a popup containing
 * Elemacy's own Form and Loop Carousel widgets plus a fixture "third-party
 * addon" widget (registered by fixtures/mu-plugins/popup-third-party-widget.php),
 * each with its own script dependency. Owns only its own recorded posts, even
 * during a failed run.
 */
try {
    require __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/fixtures/LibraryDocument.php';

    $directory = dirname($elemacy_test_environment['root']);
    $command = $argv[1] ?? '';

    if ($command === 'create') {
        $administrator = get_user_by('login', 'elemacy_test_admin');
        wp_set_current_user($administrator->ID);

        // Elementor's own Element Cache module (active by default) would
        // otherwise replace these widgets' output with an unexpanded
        // shortcode placeholder for this fixture's purposes — see
        // foreign-document-assets.php's own note on this. Orthogonal to
        // what this fixture verifies.
        update_option('elementor_element_cache_ttl', 'disable');

        $documents = new Elemacy\Tests\Integration\Fixtures\LibraryDocument();
        $owned_posts = [];

        try {
            $suffix = wp_generate_uuid4();

            $item_template = $documents->create('Popup assets carousel item ' . $suffix, 'loop', 'publish', Elemacy\Tests\Integration\Fixtures\LibraryDocument::heading('Carousel item'));
            $owned_posts[] = $item_template;

            $source_post = wp_insert_post([
                'post_type' => 'post', 'post_status' => 'publish',
                'post_title' => 'Popup assets carousel source ' . $suffix,
                'meta_input' => ['_elemacy_fixture_environment' => ELEMACY_INTEGRATION_TOKEN],
            ], true);
            elemacy_test_assert(!is_wp_error($source_post), 'Could not create carousel source post.');
            $owned_posts[] = $source_post;

            $popup_elements = [
                [
                    'id' => 'epb1001', 'elType' => 'widget', 'widgetType' => 'elemacy-form',
                    'settings' => [], 'elements' => [],
                ],
                [
                    'id' => 'epb1002', 'elType' => 'widget', 'widgetType' => 'elemacy-loop-carousel',
                    'settings' => ['template_id' => $item_template, 'data_source' => 'posts'], 'elements' => [],
                ],
                [
                    'id' => 'epb1003', 'elType' => 'widget', 'widgetType' => 'elemacy-fixture-thirdparty',
                    'settings' => [], 'elements' => [],
                ],
            ];
            $popup_id = $documents->create('Popup assets fixture popup ' . $suffix, 'popup', 'publish', $popup_elements);
            $owned_posts[] = $popup_id;
            // A popup created in the admin starts with one immediate Page Load trigger.
            update_post_meta($popup_id, '_elemacy_popup_triggers', [['id' => 'fixture-page-load', 'type' => 'page_load', 'params' => ['delay' => 0]]]);

            // Marks this document for fixtures/mu-plugins/template-rendering-
            // preparation.php's `elementor/frontend/the_content` filter,
            // which prepends a sibling <style> tag before the .elementor
            // root — task 3.5's browser proof that hydrate() retains a
            // prepared fragment's non-root markup instead of discarding it.
            update_post_meta($popup_id, '_elemacy_fixture_item_marker', '1');

            $host_id = wp_insert_post([
                'post_type' => 'post', 'post_status' => 'publish',
                'post_title' => 'Popup assets fixture host ' . $suffix,
                'post_content' => 'Plain content, no Elementor.',
                'meta_input' => ['_elemacy_fixture_environment' => ELEMACY_INTEGRATION_TOKEN],
            ], true);
            elemacy_test_assert(!is_wp_error($host_id), 'Could not create fixture host.');
            $owned_posts[] = $host_id;

            $fixture = [
                'token' => ELEMACY_INTEGRATION_TOKEN,
                'host_id' => $host_id,
                'popup_id' => $popup_id,
                'url' => get_permalink($host_id),
                'owned_posts' => $owned_posts,
                'manifest' => $directory . '/popup-assets-fixture-' . wp_generate_uuid4() . '.json',
            ];
            elemacy_test_assert((bool) file_put_contents($fixture['manifest'], wp_json_encode($fixture)), 'Cannot record fixture ownership.');
            chmod($fixture['manifest'], 0600);
            echo wp_json_encode($fixture) . "\n";
        } catch (Throwable $error) {
            foreach (array_reverse($owned_posts) as $id) {
                wp_delete_post($id, true);
            }
            $documents->cleanup();
            delete_option('elementor_element_cache_ttl');
            throw $error;
        }
    } elseif ($command === 'cleanup') {
        $path = realpath($argv[2] ?? '');
        elemacy_test_assert($path && dirname($path) === $directory
            && preg_match('/\Apopup-assets-fixture-[a-f0-9-]+\.json\z/', basename($path)), 'Refusing an unrelated fixture manifest.');
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
        delete_option('elementor_element_cache_ttl');
        unlink($path);
        echo "Popup assets browser fixture removed.\n";
    } else {
        throw new InvalidArgumentException('Use create or cleanup <fixture-manifest>.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Popup assets browser fixture failed: ' . $error->getMessage() . "\n");
    exit(1);
}
