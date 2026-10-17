<?php

/** Native browser session/cleanup fixture. UI creates the documents under test. */
try {
    require __DIR__ . '/bootstrap.php';
    $directory = dirname(realpath((string) getenv('ELEMACY_TEST_ENV')));
    $command = $argv[1] ?? '';
    if ($command === 'create') {
        $run = wp_generate_uuid4();
        $user = wp_insert_user([
            'user_login' => 'journey-' . $run,
            'user_pass' => wp_generate_password(48),
            'user_email' => 'journey-' . $run . '@example.invalid',
            'role' => 'administrator',
        ]);
        elemacy_test_assert(!is_wp_error($user), 'Cannot create owned journey author.');
        update_user_meta($user, '_elemacy_fixture_environment', ELEMACY_INTEGRATION_TOKEN);
        $expires = time() + 1800;
        $session = WP_Session_Tokens::get_instance($user)->create($expires);
        $fixture = [
            'environment_token' => ELEMACY_INTEGRATION_TOKEN,
            'user' => $user,
            'modules' => get_option('elemacy_active_modules', null),
            'onboarded' => get_option('elementor_onboarded', null),
            'manifest' => $directory . '/journey-fixture-' . $run . '.json',
            'cookies' => [
                ['name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie($user, $expires, 'auth', $session)],
                ['name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie($user, $expires, 'logged_in', $session)],
            ],
        ];
        elemacy_test_assert((bool) file_put_contents($fixture['manifest'], wp_json_encode($fixture)), 'Cannot record fixture ownership.');
        chmod($fixture['manifest'], 0600);
        update_option('elementor_onboarded', true);
        echo wp_json_encode($fixture);
    } elseif ($command === 'cleanup') {
        $path = realpath($argv[2] ?? '');
        elemacy_test_assert($path && dirname($path) === $directory
            && preg_match('/\Ajourney-fixture-[a-f0-9-]+\.json\z/', basename($path)), 'Refusing an unrelated journey manifest.');
        $fixture = json_decode(file_get_contents($path), true);
        elemacy_test_assert(($fixture['environment_token'] ?? '') === ELEMACY_INTEGRATION_TOKEN, 'Wrong journey environment.');
        $user = (int) ($fixture['user'] ?? 0);
        elemacy_test_assert($user && get_user_meta($user, '_elemacy_fixture_environment', true) === ELEMACY_INTEGRATION_TOKEN,
            'Refusing to clean an unowned journey user.');
        // Ownership is the dedicated run author; no title matching or site-wide deletion.
        $documents = get_posts(['post_type' => get_post_types([], 'names'), 'post_status' => get_post_stati([], 'names'), 'author' => $user,
            'numberposts' => -1, 'fields' => 'ids']);
        foreach ($documents as $id) {
            elemacy_test_assert((int) get_post_field('post_author', $id) === $user, 'Unowned journey document.');
        }
        foreach ($documents as $id) {
            // Deleting a parent also deletes its revisions. A recorded revision
            // may therefore already be gone; never confuse that with foreign ownership.
            if (!get_post($id)) {
                continue;
            }
            elemacy_test_assert((int) get_post_field('post_author', $id) === $user, 'Unowned journey document.');
            wp_delete_post($id, true);
        }
        foreach (['modules' => 'elemacy_active_modules', 'onboarded' => 'elementor_onboarded'] as $key => $option) {
            if ($fixture[$key] === null) {
                delete_option($option);
            } else {
                update_option($option, $fixture[$key]);
            }
        }
        require_once ABSPATH . 'wp-admin/includes/user.php';
        WP_Session_Tokens::get_instance($user)->destroy_all();
        elemacy_test_assert(wp_delete_user($user), 'Journey author cleanup failed.');
        unlink($path);
        echo wp_json_encode(['cleaned' => true]);
    } else {
        throw new InvalidArgumentException('Use create or cleanup <manifest>.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Journey fixture failed: ' . $error->getMessage() . "\n");
    exit(1);
}
