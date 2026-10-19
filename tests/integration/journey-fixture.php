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
    } elseif ($command === 'seed-archive') {
        // Content for archive-preview journeys. Posts belong to the run author (cleaned with it);
        // the terms and the extra author are recorded in the manifest so cleanup removes exactly them.
        $path = realpath($argv[2] ?? '');
        elemacy_test_assert($path && dirname($path) === $directory
            && preg_match('/\Ajourney-fixture-[a-f0-9-]+\.json\z/', basename($path)), 'Refusing an unrelated journey manifest.');
        $fixture = json_decode(file_get_contents($path), true);
        elemacy_test_assert(($fixture['environment_token'] ?? '') === ELEMACY_INTEGRATION_TOKEN, 'Wrong journey environment.');
        $run = substr(basename($path, '.json'), strlen('journey-fixture-'), 8);
        $owner = (int) $fixture['user'];
        $category = wp_insert_term('Journey News ' . $run, 'category');
        $tag = wp_insert_term('Journey Tag ' . $run, 'post_tag');
        elemacy_test_assert(!is_wp_error($category) && !is_wp_error($tag), 'Cannot create journey terms.');
        $writer = wp_insert_user([
            'user_login' => 'journey-writer-' . $run,
            'user_pass' => wp_generate_password(48),
            'user_email' => 'journey-writer-' . $run . '@example.invalid',
            'display_name' => 'Journey Writer ' . $run,
            'role' => 'author',
        ]);
        $reader = wp_insert_user([
            'user_login' => 'journey-reader-' . $run,
            'user_pass' => wp_generate_password(48),
            'user_email' => 'journey-reader-' . $run . '@example.invalid',
            'display_name' => 'Journey Reader ' . $run,
            'role' => 'subscriber',
        ]);
        elemacy_test_assert(!is_wp_error($writer) && !is_wp_error($reader), 'Cannot create journey users.');
        foreach ([$writer, $reader] as $extra) {
            update_user_meta($extra, '_elemacy_fixture_environment', ELEMACY_INTEGRATION_TOKEN);
        }
        $post = wp_insert_post([
            'post_title' => 'Journey Post ' . $run,
            'post_content' => 'Journey body',
            'post_status' => 'publish',
            'post_author' => $owner,
            'post_category' => [$category['term_id']],
            'post_date' => '2024-03-15 10:00:00',
            'post_date_gmt' => get_gmt_from_date('2024-03-15 10:00:00'),
        ]);
        elemacy_test_assert(!is_wp_error($post) && $post > 0, 'Cannot create journey post.');
        $fixture['terms'] = [[(int) $category['term_id'], 'category'], [(int) $tag['term_id'], 'post_tag']];
        $fixture['users'] = [(int) $writer, (int) $reader];
        elemacy_test_assert((bool) file_put_contents($path, wp_json_encode($fixture)), 'Cannot record seeded content.');
        echo wp_json_encode([
            'run' => $run,
            'category' => ['id' => (int) $category['term_id'], 'name' => 'Journey News ' . $run],
            'tag' => ['id' => (int) $tag['term_id'], 'name' => 'Journey Tag ' . $run],
            'writer' => ['id' => (int) $writer, 'name' => 'Journey Writer ' . $run],
            'reader' => ['id' => (int) $reader, 'name' => 'Journey Reader ' . $run],
            'post' => ['id' => (int) $post, 'title' => 'Journey Post ' . $run],
        ]);
    } elseif ($command === 'seed-menu') {
        // A menu for the Nav Menu journey: Home, Services > Design, About. Recorded so cleanup deletes it and its items.
        $path = realpath($argv[2] ?? '');
        elemacy_test_assert($path && dirname($path) === $directory
            && preg_match('/\Ajourney-fixture-[a-f0-9-]+\.json\z/', basename($path)), 'Refusing an unrelated journey manifest.');
        $fixture = json_decode(file_get_contents($path), true);
        elemacy_test_assert(($fixture['environment_token'] ?? '') === ELEMACY_INTEGRATION_TOKEN, 'Wrong journey environment.');
        $run = substr(basename($path, '.json'), strlen('journey-fixture-'), 8);
        $name = 'Journey Menu ' . $run;
        $menu = wp_create_nav_menu($name);
        elemacy_test_assert(!is_wp_error($menu), 'Cannot create the journey menu.');
        $item = static function (string $title, string $url, int $parent = 0) use ($menu): int {
            $id = wp_update_nav_menu_item($menu, 0, [
                'menu-item-title' => $title, 'menu-item-url' => $url, 'menu-item-type' => 'custom',
                'menu-item-status' => 'publish', 'menu-item-parent-id' => $parent,
            ]);
            elemacy_test_assert(!is_wp_error($id), 'Cannot create journey menu item ' . $title . '.');

            return (int) $id;
        };
        $item('Home', home_url('/'));
        $item('Design', home_url('/services/design/'), $item('Services', home_url('/services/')));
        $item('About', home_url('/about/'));
        $fixture['menus'] = array_merge((array) ($fixture['menus'] ?? []), [(int) $menu]);
        elemacy_test_assert((bool) file_put_contents($path, wp_json_encode($fixture)), 'Cannot record seeded content.');
        echo wp_json_encode(['menu' => ['id' => (int) $menu, 'name' => $name, 'slug' => wp_get_nav_menu_object($menu)->slug]]);
    } elseif ($command === 'delete-term') {
        // Deletes one term this run seeded, to see what an editor shows when a saved choice is gone.
        $path = realpath($argv[2] ?? '');
        elemacy_test_assert($path && dirname($path) === $directory
            && preg_match('/\Ajourney-fixture-[a-f0-9-]+\.json\z/', basename($path)), 'Refusing an unrelated journey manifest.');
        $fixture = json_decode(file_get_contents($path), true);
        elemacy_test_assert(($fixture['environment_token'] ?? '') === ELEMACY_INTEGRATION_TOKEN, 'Wrong journey environment.');
        $term_id = (int) ($argv[3] ?? 0);
        $owned = array_values(array_filter((array) ($fixture['terms'] ?? []), static fn ($entry) => (int) $entry[0] === $term_id));
        elemacy_test_assert(1 === count($owned), 'Refusing to delete a term this run did not seed.');
        elemacy_test_assert(true === wp_delete_term($term_id, (string) $owned[0][1]), 'Cannot delete the journey term.');
        echo wp_json_encode(['deleted' => $term_id]);
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
        foreach ((array) ($fixture['terms'] ?? []) as [$term_id, $taxonomy]) {
            wp_delete_term((int) $term_id, (string) $taxonomy);
        }
        foreach ((array) ($fixture['menus'] ?? []) as $menu) {
            wp_delete_nav_menu((int) $menu);
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
        foreach ((array) ($fixture['users'] ?? []) as $extra) {
            elemacy_test_assert(get_user_meta((int) $extra, '_elemacy_fixture_environment', true) === ELEMACY_INTEGRATION_TOKEN,
                'Refusing to clean an unowned journey user.');
            elemacy_test_assert(wp_delete_user((int) $extra), 'Journey extra user cleanup failed.');
        }
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
