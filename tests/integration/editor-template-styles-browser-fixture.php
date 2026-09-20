<?php

/**
 * Real WordPress/Elementor fixture for the editor canvas regression
 * (tests/e2e/run-editor-template-styles.mjs): a page opened in the Elementor
 * editor whose theme header and footer are Theme Builder templates authored
 * with atomic elements that carry their own styles. Owns only its own recorded
 * posts and restores the active theme, even during a failed run.
 */
try {
    require __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/fixtures/LibraryDocument.php';

    $directory = dirname($elemacy_test_environment['root']);
    $command = $argv[1] ?? '';
    $fixture_theme = 'elemacy-fixture';

    if ($command === 'create') {
        // Atomic elements are opt-in on older supported Elementor releases; the
        // regression only exists where the editor authors them, so there is
        // nothing to observe (and nothing created yet to undo) without them.
        if (!\Elementor\Plugin::instance()->elements_manager->get_element_types('e-flexbox')) {
            echo wp_json_encode(['skipped' => 'The installed Elementor does not register atomic elements (e-flexbox); the editor canvas behavior under test does not apply.']) . "\n";
            exit(0);
        }

        $administrator = get_user_by('login', 'elemacy_test_admin');
        wp_set_current_user($administrator->ID);
        // The environment manifest carries no admin password; the browser
        // needs one to open the editor.
        wp_set_password('pw-' . ELEMACY_INTEGRATION_TOKEN, $administrator->ID);

        $documents = new Elemacy\Tests\Integration\Fixtures\LibraryDocument();
        $owned_posts = [];
        $previous_theme = get_option('stylesheet');

        try {
            // A classic theme whose header.php calls wp_body_open() and whose
            // footer.php calls wp_footer(), the hooks the generic Theme Builder
            // adapter renders the header and footer through.
            switch_theme($fixture_theme);
            elemacy_test_assert(get_option('stylesheet') === $fixture_theme, 'Could not activate the fixture theme.');

            $suffix = wp_generate_uuid4();

            // An atomic container whose only look is its own local style — the
            // exact shape the editor canvas mistakes for the edited page's.
            $atomic_block = static fn (string $element_id, string $style_id, string $color, string $text): array => [[
                'id' => $element_id,
                'elType' => 'e-flexbox',
                'settings' => ['classes' => ['$$type' => 'classes', 'value' => [$style_id]]],
                'elements' => [[
                    'id' => $element_id . '1',
                    'elType' => 'widget',
                    'widgetType' => 'heading',
                    'settings' => ['title' => $text, 'header_size' => 'h2'],
                    'elements' => [],
                ]],
                'isInner' => false,
                'styles' => [$style_id => [
                    'id' => $style_id,
                    'label' => 'local',
                    'type' => 'class',
                    'variants' => [[
                        'meta' => ['breakpoint' => 'desktop', 'state' => null],
                        'props' => ['background' => ['$$type' => 'background', 'value' => ['color' => ['$$type' => 'color', 'value' => $color]]]],
                        'custom_css' => null,
                    ]],
                ]],
                'interactions' => [],
                'editor_settings' => [],
                'version' => '0.0',
            ]];

            $everywhere = [['id' => 'c1', 'type' => 'general/entire_site', 'operator' => 'include', 'value' => '']];

            $header_id = $documents->create('Editor styles header ' . $suffix, 'header', 'publish', $atomic_block('ecs1001', 'e-ecs1001a', '#850202', 'Fixture header'));
            $owned_posts[] = $header_id;
            (new Elemacy\Conditions\ConditionRepository())->save($header_id, $everywhere);

            $footer_id = $documents->create('Editor styles footer ' . $suffix, 'footer', 'publish', $atomic_block('ecs2001', 'e-ecs2001a', '#025a85', 'Fixture footer'));
            $owned_posts[] = $footer_id;
            (new Elemacy\Conditions\ConditionRepository())->save($footer_id, $everywhere);

            // The edited page: native Elementor content with no atomic styles of
            // its own, so the editor has nothing to render itself until the
            // browser adds an element.
            $page = \Elementor\Plugin::instance()->documents->create(
                'wp-page',
                ['post_type' => 'page', 'post_title' => 'Editor styles page ' . $suffix, 'post_status' => 'draft']
            );
            elemacy_test_assert(!is_wp_error($page), 'Could not create the fixture page.');
            $page_id = $page->get_main_id();
            $owned_posts[] = $page_id;
            update_post_meta($page_id, '_elemacy_fixture_environment', ELEMACY_INTEGRATION_TOKEN);
            $page->save([
                'elements' => Elemacy\Tests\Integration\Fixtures\LibraryDocument::heading('Edited page'),
                'settings' => ['post_status' => 'publish'],
            ]);
            elemacy_test_assert(get_post_status($page_id) === 'publish', 'The fixture page did not publish.');

            $fixture = [
                'token' => ELEMACY_INTEGRATION_TOKEN,
                'header_id' => $header_id,
                'footer_id' => $footer_id,
                'page_id' => $page_id,
                'editor_url' => admin_url('post.php?post=' . $page_id . '&action=elementor'),
                'previous_theme' => $previous_theme,
                'owned_posts' => $owned_posts,
                'manifest' => $directory . '/editor-template-styles-fixture-' . wp_generate_uuid4() . '.json',
            ];
            elemacy_test_assert((bool) file_put_contents($fixture['manifest'], wp_json_encode($fixture)), 'Cannot record fixture ownership.');
            chmod($fixture['manifest'], 0600);
            echo wp_json_encode($fixture) . "\n";
        } catch (Throwable $error) {
            foreach (array_reverse($owned_posts) as $id) {
                wp_delete_post($id, true);
            }
            if (get_option('stylesheet') === $fixture_theme && $previous_theme) {
                switch_theme($previous_theme);
            }
            throw $error;
        }
    } elseif ($command === 'cleanup') {
        $path = realpath($argv[2] ?? '');
        elemacy_test_assert($path && dirname($path) === $directory
            && preg_match('/\Aeditor-template-styles-fixture-[a-f0-9-]+\.json\z/', basename($path)), 'Refusing an unrelated fixture manifest.');
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
        if (get_option('stylesheet') === $fixture_theme && !empty($fixture['previous_theme'])) {
            switch_theme($fixture['previous_theme']);
        }
        unlink($path);
        echo "Editor template styles browser fixture removed.\n";
    } else {
        throw new InvalidArgumentException('Use create or cleanup <fixture-manifest>.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Editor template styles browser fixture failed: ' . $error->getMessage() . "\n");
    exit(1);
}
