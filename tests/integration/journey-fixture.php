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
    } elseif ($command === 'seed-host-page') {
        // An Elementor page the popup journeys act on as a visitor: a Button linked "#" with a CSS class (a click
        // trigger target), a tall spacer, a heading with an element id deep down (scroll to element), and links to
        // the home page and to another page. Owned by the run author, so cleanup removes it.
        $path = realpath($argv[2] ?? '');
        elemacy_test_assert($path && dirname($path) === $directory
            && preg_match('/\Ajourney-fixture-[a-f0-9-]+\.json\z/', basename($path)), 'Refusing an unrelated journey manifest.');
        $fixture = json_decode(file_get_contents($path), true);
        elemacy_test_assert(($fixture['environment_token'] ?? '') === ELEMACY_INTEGRATION_TOKEN, 'Wrong journey environment.');
        $run = substr(basename($path, '.json'), strlen('journey-fixture-'), 8);
        $widget = static fn (string $id, string $type, array $settings): array => [
            'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => [],
        ];
        $popup = (int) ($argv[3] ?? 0);
        $links = $popup > 0 ? [
            $widget('jh00006', 'button', ['text' => 'Journey popup link', 'link' => ['url' => '#elemacy-popup-' . $popup, 'is_external' => '', 'nofollow' => ''], '_css_classes' => 'journey-link']),
            $widget('jh00007', 'button', ['text' => 'Journey missing popup link', 'link' => ['url' => '#elemacy-popup-999999', 'is_external' => '', 'nofollow' => ''], '_css_classes' => 'journey-missing']),
        ] : [];
        $elements = [[
            'id' => 'jh00001', 'elType' => 'container', 'settings' => [], 'elements' => array_merge($links, [
                $widget('jh00002', 'button', ['text' => 'Journey opener', 'link' => ['url' => '#', 'is_external' => '', 'nofollow' => ''], '_css_classes' => 'journey-open']),
                $widget('jh00003', 'html', ['html' => '<p><a class="journey-home" href="' . esc_url(home_url('/')) . '">Journey home link</a></p>']),
                $widget('jh00004', 'spacer', ['space' => ['unit' => 'px', 'size' => 3000]]),
                $widget('jh00005', 'heading', ['title' => 'Journey deep target', '_element_id' => 'journey-target']),
            ]),
        ]];
        $page = wp_insert_post([
            'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Journey Host ' . $run,
            'post_author' => (int) $fixture['user'],
        ]);
        elemacy_test_assert(!is_wp_error($page) && $page > 0, 'Cannot create the journey host page.');
        update_post_meta($page, '_elementor_edit_mode', 'builder');
        update_post_meta($page, '_elementor_template_type', 'wp-page');
        update_post_meta($page, '_elementor_version', defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '');
        update_post_meta($page, '_elementor_data', wp_slash(wp_json_encode($elements)));
        echo wp_json_encode(['page' => ['id' => (int) $page, 'url' => get_permalink($page), 'title' => 'Journey Host ' . $run]]);
    } elseif ($command === 'seed-user') {
        // A signed-in visitor of a given role (argument 3), for audience-rule journeys. Recorded in the manifest so
        // cleanup deletes it; returns the cookies of a session of its own.
        $path = realpath($argv[2] ?? '');
        elemacy_test_assert($path && dirname($path) === $directory
            && preg_match('/\Ajourney-fixture-[a-f0-9-]+\.json\z/', basename($path)), 'Refusing an unrelated journey manifest.');
        $fixture = json_decode(file_get_contents($path), true);
        elemacy_test_assert(($fixture['environment_token'] ?? '') === ELEMACY_INTEGRATION_TOKEN, 'Wrong journey environment.');
        $role = (string) ($argv[3] ?? '');
        elemacy_test_assert(in_array($role, ['subscriber', 'editor', 'author', 'contributor'], true), 'Unsupported journey role.');
        $run = substr(basename($path, '.json'), strlen('journey-fixture-'), 8);
        $user = wp_insert_user([
            'user_login' => 'journey-' . $role . '-' . $run,
            'user_pass' => wp_generate_password(48),
            'user_email' => 'journey-' . $role . '-' . $run . '@example.invalid',
            'role' => $role,
        ]);
        elemacy_test_assert(!is_wp_error($user), 'Cannot create the journey ' . $role . '.');
        update_user_meta($user, '_elemacy_fixture_environment', ELEMACY_INTEGRATION_TOKEN);
        $expires = time() + 1800;
        $session = WP_Session_Tokens::get_instance($user)->create($expires);
        $fixture['users'] = array_merge((array) ($fixture['users'] ?? []), [(int) $user]);
        elemacy_test_assert((bool) file_put_contents($path, wp_json_encode($fixture)), 'Cannot record the journey user.');
        echo wp_json_encode(['user' => (int) $user, 'cookies' => [
            ['name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie($user, $expires, 'auth', $session)],
            ['name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie($user, $expires, 'logged_in', $session)],
        ]]);
    } elseif ($command === 'seed-popup-content') {
        // Content a popup journey interacts with: a form, an image carousel and a video element. The popup itself is
        // created and published through the UI; this only fills a popup the run author owns (argument 3).
        $path = realpath($argv[2] ?? '');
        elemacy_test_assert($path && dirname($path) === $directory
            && preg_match('/\Ajourney-fixture-[a-f0-9-]+\.json\z/', basename($path)), 'Refusing an unrelated journey manifest.');
        $fixture = json_decode(file_get_contents($path), true);
        elemacy_test_assert(($fixture['environment_token'] ?? '') === ELEMACY_INTEGRATION_TOKEN, 'Wrong journey environment.');
        $popup = (int) ($argv[3] ?? 0);
        elemacy_test_assert($popup > 0 && 'elemacy_library' === get_post_type($popup)
            && (int) get_post_field('post_author', $popup) === (int) $fixture['user'], 'Refusing to fill a popup this run did not create.');
        $widget = static fn (string $id, string $type, array $settings): array => [
            'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => [],
        ];
        $elements = [[
            'id' => 'jp00001', 'elType' => 'container', 'settings' => [], 'elements' => [
                $widget('jp00002', 'heading', ['title' => 'Journey interaction popup']),
                $widget('jp00003', 'elemacy-form', ['form_fields' => [
                    ['type' => 'text', 'label' => 'Name', 'required' => 'yes'],
                    ['type' => 'email', 'label' => 'Email', 'required' => 'yes'],
                ], 'submit_actions' => ['email'], 'email_to' => 'journey@example.invalid']),
                $widget('jp00004', 'image-carousel', ['slides_to_show' => '1', 'carousel' => array_map(static fn (int $n): array => ['id' => '', 'url' => \Elementor\Utils::get_placeholder_image_src()], [1, 2, 3])]),
                $widget('jp00005', 'html', ['html' => '<video id="journey-video" muted loop width="120" height="68"></video>']),
            ],
        ]];
        update_post_meta($popup, '_elementor_data', wp_slash(wp_json_encode($elements)));
        delete_post_meta($popup, '_elementor_css');
        echo wp_json_encode(['popup' => $popup]);
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
