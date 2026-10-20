<?php

/**
 * Fixture for run-topbar-prerender.mjs: a default Top Bar holding a classic Button and an atomic heading,
 * published on the Hello Elementor theme. `create` switches the theme and returns the page to visit;
 * `cleanup <manifest>` removes the bar and puts the previous theme back.
 */
try {
    require __DIR__ . '/bootstrap.php';
    $directory = dirname(realpath((string) getenv('ELEMACY_TEST_ENV')));
    $command = $argv[1] ?? '';

    if ('create' === $command) {
        elemacy_test_assert(wp_get_theme('hello-elementor')->exists(), 'Provision with --hello-elementor-theme.');
        elemacy_test_assert(\Elementor\Plugin::instance()->widgets_manager->get_widget_types('e-heading') !== null, 'Needs atomic elements.');
        wp_set_current_user(get_user_by('login', 'elemacy_test_admin')->ID);
        update_option('elementor_element_cache_ttl', 'disable');
        $previous = get_stylesheet();

        $text = static function (string $type, string $prop, string $value): array {
            $widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types($type);
            $accepted = array_keys($widget::get_props_schema()[$prop]->get_prop_types());

            return in_array('escaped-html', $accepted, true)
                ? ['$$type' => 'escaped-html', 'value' => $value]
                : ['$$type' => 'html-v3', 'value' => ['content' => ['$$type' => 'string', 'value' => $value], 'children' => []]];
        };
        $atomic = [
            'id' => 'tb00002', 'elType' => 'widget', 'widgetType' => 'e-heading', 'isInner' => false,
            'settings' => ['classes' => ['$$type' => 'classes', 'value' => ['e-tb2a']], 'title' => $text('e-heading', 'title', 'Atomic bar heading')],
            'styles' => [
                'e-tb2a' => [
                    'id' => 'e-tb2a', 'label' => 'local', 'type' => 'class',
                    'variants' => [['meta' => ['breakpoint' => 'desktop', 'state' => null], 'props' => ['color' => ['$$type' => 'color', 'value' => '#123401']], 'custom_css' => null]],
                ],
            ],
            'elements' => [], 'interactions' => [], 'editor_settings' => [], 'version' => '0.0',
        ];
        $button = ['id' => 'tb00003', 'elType' => 'widget', 'widgetType' => 'button', 'settings' => [
            'text' => 'Classic bar button', 'link' => ['url' => '#', 'is_external' => '', 'nofollow' => ''], '_css_classes' => 'tb-classic-button',
        ], 'elements' => []];
        $heading = ['id' => 'tb00004', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => ['title' => 'Classic bar heading'], 'elements' => []];
        $elements = [['id' => 'tb00001', 'elType' => 'container', 'settings' => [], 'elements' => [$heading, $button, $atomic]]];

        $documents = new Elemacy\Tests\Integration\Fixtures\LibraryDocument();
        // Saved in the editor's own shape, written directly: the save API refuses an atomic payload outside the editor.
        $popup = $documents->create('Prerender bar', 'popup', 'publish');
        update_post_meta($popup, '_elementor_data', wp_slash(wp_json_encode($elements)));
        delete_post_meta($popup, '_elementor_css');
        update_post_meta($popup, '_elemacy_template_type', 'topbar');
        (new Elemacy\Conditions\ConditionRepository())->save($popup, Elemacy\TemplateLibrary\TypeRegistry::instance()->get('topbar')->default_conditions);
        $host = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Prerender host', 'post_content' => 'Content below the bar.']);
        switch_theme('hello-elementor');

        $fixture = [
            'token' => ELEMACY_INTEGRATION_TOKEN, 'popup' => $popup, 'host' => $host, 'previous_theme' => $previous,
            'manifest' => $directory . '/topbar-prerender-' . wp_generate_uuid4() . '.json',
        ];
        file_put_contents($fixture['manifest'], wp_json_encode($fixture));
        chmod($fixture['manifest'], 0600);
        echo wp_json_encode($fixture + ['url' => get_permalink($host)]);
    } elseif ('cleanup' === $command) {
        $path = realpath($argv[2] ?? '');
        elemacy_test_assert($path && dirname($path) === $directory && 1 === preg_match('/\Atopbar-prerender-[a-f0-9-]+\.json\z/', basename($path)), 'Refusing an unrelated manifest.');
        $fixture = json_decode(file_get_contents($path), true);
        elemacy_test_assert(($fixture['token'] ?? '') === ELEMACY_INTEGRATION_TOKEN, 'Wrong environment.');
        switch_theme((string) $fixture['previous_theme']);
        wp_delete_post((int) $fixture['popup'], true);
        wp_delete_post((int) $fixture['host'], true);
        delete_option('elementor_element_cache_ttl');
        unlink($path);
        echo wp_json_encode(['cleaned' => true]);
    } else {
        throw new InvalidArgumentException('Use create or cleanup <manifest>.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Fixture failed: ' . $error->getMessage() . "\n");
    exit(1);
}
