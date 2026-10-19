<?php

/**
 * Real WordPress/Elementor fixture for the Nav Menu browser runners
 * (tests/e2e/run-nav-menu.mjs and run-nav-menu-parity.mjs).
 *
 *   create                         a three-level menu and one page per scenario
 *   breakpoint <manifest> <px>     sets the kit's Tablet breakpoint (restored by cleanup)
 *   cleanup <manifest>             removes exactly what create recorded
 *
 * The menu is Home, Services > (Design > Branding, Strategy), About (#about),
 * Contact (#contact) and Edge > Edge child > Edge grandchild; the pages
 * right-align it so Edge's nested submenus must flip to stay on screen. With
 * Elementor Pro active, create also records parity pages: Pro's Nav Menu and
 * Elemacy's with identical settings and element IDs, one widget per page, plus
 * one page holding both.
 */
try {
    require __DIR__ . '/bootstrap.php';

    $directory = dirname($elemacy_test_environment['root']);
    $command = $argv[1] ?? '';

    /** A container holding the given widgets, as the editor saves it. */
    function elemacy_nm_container(string $id, array $widgets, array $settings = []): array
    {
        return ['id' => $id, 'elType' => 'container', 'settings' => $settings, 'elements' => $widgets];
    }

    function elemacy_nm_widget(string $id, string $type, array $settings): array
    {
        return ['id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => []];
    }

    function elemacy_nm_manifest(string $directory, string $path): array
    {
        $real = realpath($path);
        elemacy_test_assert($real && dirname($real) === $directory
            && preg_match('/\Anav-menu-fixture-[a-f0-9-]+\.json\z/', basename($real)), 'Refusing an unrelated fixture manifest.');
        $fixture = json_decode(file_get_contents($real), true);
        elemacy_test_assert(($fixture['token'] ?? '') === ELEMACY_INTEGRATION_TOKEN, 'Fixture belongs to another environment.');

        return [$real, $fixture];
    }

    /**
     * Settings the parity runner renders with both widgets. Each variant
     * exercises a different part of the shared control surface.
     */
    function elemacy_nm_parity_variants(string $menu): array
    {
        $size = static fn ($value, string $unit = 'px'): array => ['unit' => $unit, 'size' => $value, 'sizes' => []];

        return [
            'defaults'   => ['menu' => $menu],
            'overline'   => ['menu' => $menu, 'pointer' => 'overline', 'animation_line' => 'drop-in', 'nav_menu_divider' => 'yes',
                'nav_menu_divider_style' => 'dotted', 'nav_menu_divider_weight' => $size(2), 'nav_menu_divider_height' => $size(50, '%'),
                'menu_space_between' => $size(16), 'padding_horizontal_menu_item' => $size(12), 'color_menu_item' => '#222222',
                'pointer_color_menu_item_hover' => '#cc3366'],
            'framed'     => ['menu' => $menu, 'pointer' => 'framed', 'animation_framed' => 'corners', 'pointer_width' => $size(4), 'align_items' => 'center'],
            'background' => ['menu' => $menu, 'pointer' => 'background', 'animation_background' => 'shutter-in-horizontal',
                'border_radius_menu_item' => $size(6), 'color_menu_item_hover_pointer_bg' => '#ffffee', 'align_items' => 'justify'],
            'vertical'   => ['menu' => $menu, 'layout' => 'vertical', 'pointer' => 'text', 'animation_text' => 'skew', 'align_items' => 'end',
                'padding_vertical_menu_item' => $size(6)],
            'dropdown'   => ['menu' => $menu, 'layout' => 'dropdown', 'toggle_align' => 'right', 'toggle_size' => $size(30),
                'toggle_background_color' => '#eeeeee', 'toggle_border_radius' => $size(8), 'text_align' => 'center',
                'background_color_dropdown_item' => '#fafafa', 'color_dropdown_item_hover' => '#ffffff', 'background_color_dropdown_item_hover' => '#334455',
                'dropdown_border_radius' => ['unit' => 'px', 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'isLinked' => true],
                'dropdown_divider_border' => 'solid', 'dropdown_divider_color' => '#dddddd', 'dropdown_divider_width' => $size(1),
                'dropdown_top_distance' => $size(4)],
        ];
    }

    if ($command === 'create') {
        $administrator = get_user_by('login', 'elemacy_test_admin');
        wp_set_current_user($administrator->ID);
        // The environment manifest carries no admin password; the editor scenario signs in with this one.
        wp_set_password('pw-' . ELEMACY_INTEGRATION_TOKEN, $administrator->ID);

        $previous_structure = get_option('permalink_structure');
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure('/%postname%/');
        delete_option('rewrite_rules');

        $owned_posts = [];
        $menu_id = 0;

        try {
            $suffix = substr(wp_generate_uuid4(), 0, 8);
            $menu_id = wp_create_nav_menu('Nav Menu fixture ' . $suffix);
            elemacy_test_assert(!is_wp_error($menu_id), 'Could not create the fixture menu.');
            $menu = wp_get_nav_menu_object($menu_id)->slug;

            $page = static function (string $title, array $elements) use (&$owned_posts): int {
                $document = Elementor\Plugin::instance()->documents->create('wp-page', [
                    'post_type' => 'page', 'post_title' => $title . ' ' . bin2hex(random_bytes(3)), 'post_status' => 'draft',
                ]);
                elemacy_test_assert(!is_wp_error($document), 'Could not create ' . $title . '.');
                $id = $document->get_main_id();
                $owned_posts[] = $id;
                update_post_meta($id, '_elemacy_fixture_environment', ELEMACY_INTEGRATION_TOKEN);
                update_post_meta($id, '_wp_page_template', 'elementor_canvas');
                elemacy_test_assert((bool) $document->save(['elements' => $elements, 'settings' => ['post_status' => 'publish']]), 'Could not save ' . $title . '.');

                return $id;
            };

            $nav = static fn (string $id, array $settings = []): array => elemacy_nm_widget($id, 'elemacy_nav_menu', $settings + ['menu' => $menu, 'align_items' => 'end']);
            $tall = static fn (string $id, string $html): array => elemacy_nm_widget($id, 'html', ['html' => $html]);

            $pages = [
                'horizontal' => $page('Nav horizontal', [elemacy_nm_container('nmc0001', [$nav('nmw0001'), $tall('nmh0001', '<div style="height:1600px"></div>')])]),
                // A vertical menu sits in a narrow column; full width, its submenus would rightly flip to the left.
                'vertical'   => $page('Nav vertical', [elemacy_nm_container('nmc0002', [$nav('nmw0002', ['layout' => 'vertical', 'align_items' => 'start',
                    '_element_width' => 'initial', '_element_custom_width' => ['unit' => 'px', 'size' => 220, 'sizes' => []]])])]),
                'dropdown'   => $page('Nav dropdown layout', [elemacy_nm_container('nmc0003', [$nav('nmw0003', ['layout' => 'dropdown'])])]),
                'full_width' => $page('Nav full width', [elemacy_nm_container('nmc0004', [$nav('nmw0004', ['full_width' => 'stretch', 'toggle_align' => 'right'])], ['content_width' => 'boxed', 'boxed_width' => ['unit' => 'px', 'size' => 600, 'sizes' => []]])]),
                'sticky'     => $page('Nav sticky', [
                    elemacy_nm_container('nmc0005', [
                        $tall('nmh0005', '<style>.elemacy-nm-sticky{position:sticky;top:0;z-index:20;background:#fff}</style>'),
                        $nav('nmw0005'),
                    ], ['css_classes' => 'elementor-sticky elemacy-nm-sticky']),
                    elemacy_nm_container('nmc0006', [$tall('nmh0006', '<div style="height:3000px"></div>')]),
                ]),
                'one_page'   => $page('Nav one page', [
                    elemacy_nm_container('nmc0007', [$nav('nmw0007', ['dropdown' => 'none', 'align_items' => 'start'])], ['css_classes' => 'elemacy-nm-sticky']),
                    elemacy_nm_container('nmc0008', [$tall('nmh0008',
                        '<style>.elemacy-nm-sticky{position:sticky;top:0;z-index:20;background:#fff}</style>'
                        . '<div id="top" style="height:1200px">Top</div>'
                        . '<section id="about" style="height:1200px">About section</section>'
                        . '<section id="contact" style="height:1200px">Contact section</section>'
                        . '<div style="height:1200px">End</div>')]),
                ]),
                'two'        => $page('Nav two widgets', [elemacy_nm_container('nmc0009', [$nav('nmw0009'), $nav('nmw0010', ['pointer' => 'background'])])]),
            ];

            $one_page_url = get_permalink($pages['one_page']);
            $item = static function (string $title, string $url, int $parent = 0) use ($menu_id): int {
                $id = wp_update_nav_menu_item($menu_id, 0, [
                    'menu-item-title' => $title, 'menu-item-url' => $url, 'menu-item-type' => 'custom',
                    'menu-item-status' => 'publish', 'menu-item-parent-id' => $parent,
                ]);
                elemacy_test_assert(!is_wp_error($id), 'Could not create menu item ' . $title . '.');

                return (int) $id;
            };
            $item('Home', home_url('/'));
            $services = $item('Services', home_url('/services/'));
            $design = $item('Design', home_url('/services/design/'), $services);
            $item('Branding', home_url('/services/design/branding/'), $design);
            $item('Strategy', home_url('/services/strategy/'), $services);
            $item('About', $one_page_url . '#about');
            $item('Contact', $one_page_url . '#contact');
            $edge = $item('Edge', home_url('/edge/'));
            $item('Edge grandchild', home_url('/edge/child/grandchild/'), $item('Edge child', home_url('/edge/child/'), $edge));

            $fixture = [
                'token' => ELEMACY_INTEGRATION_TOKEN,
                'menu' => $menu,
                'urls' => array_map('get_permalink', $pages),
                'editor_url' => admin_url('post.php?post=' . $pages['full_width'] . '&action=elementor'),
                'parity' => null,
                'owned_posts' => $owned_posts,
                'menu_id' => (int) $menu_id,
                'previous_permalink_structure' => $previous_structure,
                'manifest' => $directory . '/nav-menu-fixture-' . wp_generate_uuid4() . '.json',
            ];

            if (defined('ELEMENTOR_PRO_VERSION')) {
                $parity = ['elementor_pro' => ELEMENTOR_PRO_VERSION, 'variants' => []];

                $variants = elemacy_nm_parity_variants($menu);

                foreach ($variants as $name => $settings) {
                    $parity['variants'][$name] = [
                        'reference' => get_permalink($page('Parity ' . $name . ' reference', [elemacy_nm_container('nmp0001', [elemacy_nm_widget('nmp0002', 'nav-menu', $settings)])])),
                        'elemacy'   => get_permalink($page('Parity ' . $name . ' elemacy', [elemacy_nm_container('nmp0001', [elemacy_nm_widget('nmp0002', 'elemacy_nav_menu', $settings)])])),
                    ];
                }

                // Each widget with another variant's settings, so each can be compared with its own page.
                $parity['together'] = [
                    'url'       => get_permalink($page('Parity together', [elemacy_nm_container('nmp0003', [
                        elemacy_nm_widget('nmp0004', 'nav-menu', $variants['framed']),
                        elemacy_nm_widget('nmp0005', 'elemacy_nav_menu', $variants['background']),
                    ])])),
                    'reference' => ['element' => 'nmp0004', 'variant' => 'framed'],
                    'elemacy'   => ['element' => 'nmp0005', 'variant' => 'background'],
                ];
                $fixture['parity'] = $parity;
                $fixture['owned_posts'] = $owned_posts;
            }

            elemacy_test_assert((bool) file_put_contents($fixture['manifest'], wp_json_encode($fixture)), 'Cannot record fixture ownership.');
            chmod($fixture['manifest'], 0600);
            echo wp_json_encode($fixture) . "\n";
        } catch (Throwable $error) {
            foreach (array_reverse($owned_posts) as $id) {
                wp_delete_post($id, true);
            }
            if ($menu_id && !is_wp_error($menu_id)) {
                wp_delete_nav_menu($menu_id);
            }
            $wp_rewrite->set_permalink_structure($previous_structure);
            delete_option('rewrite_rules');
            throw $error;
        }
    } elseif ($command === 'breakpoint') {
        [$path, $fixture] = elemacy_nm_manifest($directory, $argv[2] ?? '');
        $value = (int) ($argv[3] ?? 0);
        elemacy_test_assert($value > 0, 'Give a breakpoint value in pixels.');

        $kit = Elementor\Plugin::instance()->kits_manager->get_active_id();
        $stored = get_post_meta($kit, '_elementor_page_settings', true);

        if (!array_key_exists('previous_kit_settings', $fixture)) {
            $fixture['previous_kit_settings'] = $stored;
        }

        $settings = is_array($stored) ? $stored : [];
        $settings['viewport_tablet'] = $value;
        update_post_meta($kit, '_elementor_page_settings', $settings);
        Elementor\Plugin::instance()->files_manager->clear_cache();
        file_put_contents($path, wp_json_encode($fixture));
        echo wp_json_encode(['viewport_tablet' => $value]) . "\n";
    } elseif ($command === 'cleanup') {
        [$path, $fixture] = elemacy_nm_manifest($directory, $argv[2] ?? '');
        foreach (array_reverse($fixture['owned_posts'] ?? []) as $id) {
            $id = (int) $id;
            if (!$id || !get_post($id)) {
                continue;
            }
            elemacy_test_assert(get_post_meta($id, '_elemacy_fixture_environment', true) === ELEMACY_INTEGRATION_TOKEN, 'Refusing to delete an unowned post.');
            wp_delete_post($id, true);
            elemacy_test_assert(!get_post($id), 'Fixture cleanup failed for post ' . $id . '.');
        }
        if (!empty($fixture['menu_id'])) {
            wp_delete_nav_menu((int) $fixture['menu_id']);
            elemacy_test_assert(!wp_get_nav_menu_object((int) $fixture['menu_id']), 'Fixture menu cleanup failed.');
        }
        if (array_key_exists('previous_kit_settings', $fixture)) {
            $kit = Elementor\Plugin::instance()->kits_manager->get_active_id();
            if (is_array($fixture['previous_kit_settings'])) {
                update_post_meta($kit, '_elementor_page_settings', $fixture['previous_kit_settings']);
            } else {
                delete_post_meta($kit, '_elementor_page_settings');
            }
            Elementor\Plugin::instance()->files_manager->clear_cache();
        }
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure((string) ($fixture['previous_permalink_structure'] ?? ''));
        delete_option('rewrite_rules');
        unlink($path);
        echo "Nav Menu browser fixture removed.\n";
    } else {
        throw new InvalidArgumentException('Use create, breakpoint <manifest> <px> or cleanup <manifest>.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Nav Menu browser fixture failed: ' . $error->getMessage() . "\n");
    exit(1);
}
