<?php

/** Disposable ordinary, Theme Builder and popup form browser fixture. */
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

            $elements = [[
                'id' => 'form123', 'elType' => 'widget', 'widgetType' => 'elemacy-form',
                'settings' => ['form_fields' => [
                    ['type' => 'text', 'label' => 'Name', 'required' => 'yes'],
                    ['type' => 'email', 'label' => 'Email', 'required' => 'yes'],
                ], 'submit_actions' => ['email'], 'email_to' => 'fixture@example.invalid'],
                'elements' => [],
            ]];
            $document = \Elementor\Plugin::instance()->documents->create('wp-page', [
                'post_type' => 'page', 'post_title' => 'Form submissions host ' . $suffix, 'post_status' => 'draft',
            ]);
            elemacy_test_assert(!is_wp_error($document), 'Could not create form page.');
            $host_id = $document->get_main_id();
            $owned_posts[] = $host_id;
            update_post_meta($host_id, '_elemacy_fixture_environment', ELEMACY_INTEGRATION_TOKEN);
            elemacy_test_assert($document->save(['elements' => $elements, 'settings' => ['post_status' => 'publish']]), 'Could not save form page.');
            $header_id = $documents->create_everywhere('Form submissions header ' . $suffix, 'header', 'publish', $elements);
            $owned_posts[] = $header_id;
            $popup_id = $documents->create('Form submissions popup ' . $suffix, 'popup', 'publish', $elements);
            $owned_posts[] = $popup_id;

            $fixture = [
                'token' => ELEMACY_INTEGRATION_TOKEN,
                'host_id' => $host_id,
                'popup_id' => $popup_id,
                'header_id' => $header_id,
                'url' => get_permalink($host_id),
                'owned_posts' => $owned_posts,
                'manifest' => $directory . '/form-submissions-fixture-' . wp_generate_uuid4() . '.json',
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
            && preg_match('/\Aform-submissions-fixture-[a-f0-9-]+\.json\z/', basename($path)), 'Refusing an unrelated fixture manifest.');
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
        echo "Form submissions browser fixture removed.\n";
    } else {
        throw new InvalidArgumentException('Use create or cleanup <fixture-manifest>.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Form submissions browser fixture failed: ' . $error->getMessage() . "\n");
    exit(1);
}
