<?php

/** Own only the browser fixture's recorded posts, even during failed runs. */
try {
    require __DIR__ . '/bootstrap.php';
    $directory = dirname($elemacy_test_environment['root']);
    $command = $argv[1] ?? '';
    if ($command === 'create') {
        $administrator = get_user_by('login', 'elemacy_test_admin');
        wp_set_current_user($administrator->ID);
        $documents = new Elemacy\Tests\Integration\Fixtures\LibraryDocument();
        $host = 0;
        try {
            $marker = 'Foreign Elementor document ' . wp_generate_uuid4();
            $document = $documents->create($marker);
            update_post_meta($document, '_elemacy_fixture_environment', ELEMACY_INTEGRATION_TOKEN);
            $host = wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => 'Non-Elementor fixture host',
                'post_content' => '[elemacy_fixture_document]',
                'meta_input' => [
                    '_elemacy_fixture_environment' => ELEMACY_INTEGRATION_TOKEN,
                    '_elemacy_fixture_document' => $document,
                ],
            ], true);
            if (is_wp_error($host)) {
                throw new RuntimeException($host->get_error_message());
            }
            elemacy_test_assert(!get_post_meta($host, '_elementor_edit_mode', true), 'Host must use the native editor.');
            $fixture = [
                'token' => ELEMACY_INTEGRATION_TOKEN,
                'host_id' => $host,
                'document_id' => $document,
                'marker' => $marker,
                'url' => get_permalink($host),
                'manifest' => $directory . '/browser-fixture-' . wp_generate_uuid4() . '.json',
            ];
            elemacy_test_assert((bool) file_put_contents($fixture['manifest'], wp_json_encode($fixture)), 'Cannot record fixture ownership.');
            chmod($fixture['manifest'], 0600);
            echo wp_json_encode($fixture) . "\n";
        } catch (Throwable $error) {
            if (is_int($host) && $host > 0) {
                wp_delete_post($host, true);
            }
            $documents->cleanup();
            throw $error;
        }
    } elseif ($command === 'cleanup') {
        $path = realpath($argv[2] ?? '');
        elemacy_test_assert($path && dirname($path) === $directory
            && preg_match('/\Abrowser-fixture-[a-f0-9-]+\.json\z/', basename($path)), 'Refusing an unrelated fixture manifest.');
        $fixture = json_decode(file_get_contents($path), true);
        elemacy_test_assert(($fixture['token'] ?? '') === ELEMACY_INTEGRATION_TOKEN, 'Fixture belongs to another environment.');
        foreach (['host_id', 'document_id'] as $key) {
            $id = (int) ($fixture[$key] ?? 0);
            if (!$id || !get_post($id)) {
                continue;
            }
            elemacy_test_assert(get_post_meta($id, '_elemacy_fixture_environment', true) === ELEMACY_INTEGRATION_TOKEN,
                'Refusing to delete an unowned post.');
            wp_delete_post($id, true);
            elemacy_test_assert(!get_post($id), 'Fixture cleanup failed.');
        }
        unlink($path);
        echo "Browser fixture removed.\n";
    } else {
        throw new InvalidArgumentException('Use create or cleanup <fixture-manifest>.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Browser fixture failed: ' . $error->getMessage() . "\n");
    exit(1);
}
