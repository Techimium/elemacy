<?php

/**
 * Real WordPress/Elementor fixture for the nested ACF repeater browser journey
 * (tests/e2e/run-nested-repeater-load-more.mjs). Creates, with ACF Pro:
 *
 *  - a stored field group sections > items > tags and a post holding
 *    Fruits (Apple: red, sweet, round; Banana: yellow, long) and
 *    Vegetables (Carrot: orange);
 *  - three Loop Item templates: a section card with a Parent Row items loop,
 *    an item card with a Parent Row tags loop (both Load More, one row per
 *    page), and a tag card reading the tag, its item and its section;
 *  - a published page with one Loop Grid over the sections.
 *
 * The section card previews the first section, so the editor journey sees its
 * items. Owns only what it records, even during a failed run.
 */
try {
    require __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/fixtures/LibraryDocument.php';

    $directory = dirname($elemacy_test_environment['root']);
    $command = $argv[1] ?? '';

    function elemacy_nr_tag(string $key, int $level, string $before): string
    {
        $settings = ['key' => $key, 'before' => $before, 'after' => ']'] + ($level > 0 ? ['loop_level' => (string) $level] : []);

        return '[elementor-tag id="' . substr(md5($key . $level . $before), 0, 7) . '" name="elemacy-acf-field" settings="' . rawurlencode((string) wp_json_encode($settings)) . '"]';
    }

    function elemacy_nr_heading(string $id, string $tag): array
    {
        return ['id' => $id, 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => [
            'title' => '', 'header_size' => 'h3', 'title_color' => '#B40000', '__dynamic__' => ['title' => $tag],
        ], 'elements' => []];
    }

    function elemacy_nr_grid(string $id, array $settings): array
    {
        return ['id' => $id, 'elType' => 'widget', 'widgetType' => 'elemacy-loop-grid', 'settings' => $settings, 'elements' => []];
    }

    function elemacy_nr_container(string $id, array $elements): array
    {
        return [['id' => $id, 'elType' => 'container', 'settings' => [], 'elements' => $elements]];
    }

    function elemacy_nr_manifest(string $directory, string $path): array
    {
        $real = realpath($path);
        elemacy_test_assert($real && dirname($real) === $directory
            && preg_match('/\Anested-repeater-fixture-[a-f0-9-]+\.json\z/', basename($real)), 'Refusing an unrelated fixture manifest.');
        $fixture = json_decode(file_get_contents($real), true);
        elemacy_test_assert(($fixture['token'] ?? '') === ELEMACY_INTEGRATION_TOKEN, 'Fixture belongs to another environment.');

        return [$real, $fixture];
    }

    if ($command === 'create') {
        if (!function_exists('acf_is_field_type') || !acf_is_field_type('repeater') || !function_exists('acf_import_field_group')) {
            echo wp_json_encode(['skip' => 'Requires ACF Pro (provision with --acf-plugin pointing at ACF Pro).']) . "\n";
            exit(0);
        }

        $administrator = get_user_by('login', 'elemacy_test_admin');
        wp_set_current_user($administrator->ID);
        // The environment manifest carries no admin password; the editor journey needs one to sign in.
        wp_set_password('pw-' . ELEMACY_INTEGRATION_TOKEN, $administrator->ID);
        $previous_cache_ttl = get_option('elementor_element_cache_ttl');
        update_option('elementor_element_cache_ttl', 'disable');

        $documents = new \Elemacy\Tests\Integration\Fixtures\LibraryDocument();
        $owned_posts = [];
        $group_id = 0;

        try {
            $s = substr(md5(wp_generate_uuid4()), 0, 8);
            $group = acf_import_field_group([
                'key' => "group_nr_$s", 'title' => 'Nested repeater journey ' . $s,
                'fields' => [['key' => "field_nr_sections_$s", 'name' => "sections_$s", 'label' => 'Sections', 'type' => 'repeater', 'sub_fields' => [
                    ['key' => "field_nr_title_$s", 'name' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => "field_nr_items_$s", 'name' => 'items', 'label' => 'Items', 'type' => 'repeater', 'sub_fields' => [
                        ['key' => "field_nr_name_$s", 'name' => 'name', 'label' => 'Name', 'type' => 'text'],
                        ['key' => "field_nr_tags_$s", 'name' => 'tags', 'label' => 'Tags', 'type' => 'repeater', 'sub_fields' => [
                            ['key' => "field_nr_tag_$s", 'name' => 'tag', 'label' => 'Tag', 'type' => 'text'],
                        ]],
                    ]],
                ]]],
                'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'post']]],
            ]);
            $group_id = (int) ($group['ID'] ?? 0);
            elemacy_test_assert($group_id > 0, 'Could not store the nested field group.');

            $source = wp_insert_post([
                'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Nested repeater source ' . $s,
                'meta_input' => ['_elemacy_fixture_environment' => ELEMACY_INTEGRATION_TOKEN],
            ], true);
            elemacy_test_assert(!is_wp_error($source), 'Could not create the source post.');
            $owned_posts[] = $source;
            elemacy_test_assert((bool) update_field("field_nr_sections_$s", [
                ['title' => 'Fruits', 'items' => [
                    ['name' => 'Apple', 'tags' => [['tag' => 'red'], ['tag' => 'sweet'], ['tag' => 'round']]],
                    ['name' => 'Banana', 'tags' => [['tag' => 'yellow'], ['tag' => 'long']]],
                ]],
                ['title' => 'Vegetables', 'items' => [['name' => 'Carrot', 'tags' => [['tag' => 'orange']]]]],
            ], $source), 'Could not save the nested rows.');

            $paged = ['pagination_type' => 'load_more', 'rows_number' => 1];
            $tag = $documents->create('Nested tag card ' . $s, 'loop', 'publish', elemacy_nr_container('nrc1', [
                elemacy_nr_heading('nrh1', elemacy_nr_tag('tag', 0, '[T=')),
                elemacy_nr_heading('nrh2', elemacy_nr_tag('name', 1, '[TI=')),
                elemacy_nr_heading('nrh3', elemacy_nr_tag('title', 2, '[TS=')),
            ]));
            $item = $documents->create('Nested item card ' . $s, 'loop', 'publish', elemacy_nr_container('nrc2', [
                elemacy_nr_heading('nrh4', elemacy_nr_tag('name', 0, '[I=')),
                elemacy_nr_grid('nrtags', ['data_source' => 'acf_repeater', 'repeater_field' => 'tags', 'source_type' => 'parent_row', 'template_id' => $tag] + $paged),
            ]));
            $section = $documents->create('Nested section card ' . $s, 'loop', 'publish', elemacy_nr_container('nrc3', [
                elemacy_nr_heading('nrh5', elemacy_nr_tag('title', 0, '[S=')),
                elemacy_nr_grid('nritems', ['data_source' => 'acf_repeater', 'repeater_field' => 'items', 'source_type' => 'parent_row', 'template_id' => $item] + $paged),
            ]));
            $page_settings = get_post_meta($section, '_elementor_page_settings', true) ?: [];
            update_post_meta($section, '_elementor_page_settings', [
                'preview_data_source' => 'acf_repeater', 'repeater_field' => "sections_$s",
                'source_type' => 'specific_post', 'specific_post_id' => $source,
            ] + $page_settings);

            $host = wp_insert_post([
                'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Nested repeater journey ' . $s,
                'meta_input' => ['_elemacy_fixture_environment' => ELEMACY_INTEGRATION_TOKEN],
            ], true);
            elemacy_test_assert(!is_wp_error($host), 'Could not create the host page.');
            $owned_posts[] = $host;
            update_post_meta($host, '_elementor_edit_mode', 'builder');
            update_post_meta($host, '_elementor_version', ELEMENTOR_VERSION);
            update_post_meta($host, '_elementor_data', wp_slash((string) wp_json_encode(elemacy_nr_container('nrc4', [
                elemacy_nr_grid('nrouter', [
                    'data_source' => 'acf_repeater', 'repeater_field' => "sections_$s", 'source_type' => 'specific_post',
                    'specific_post_id' => $source, 'template_id' => $section, 'rows_number' => 0,
                ]),
            ]))));

            $fixture = [
                'token' => ELEMACY_INTEGRATION_TOKEN,
                'page_url' => get_permalink($host),
                'editor_url' => admin_url('post.php?post=' . $section . '&action=elementor'),
                'owned_posts' => array_merge($owned_posts, [$tag, $item, $section]),
                'field_group' => $group_id,
                'previous_cache_ttl' => $previous_cache_ttl,
                'manifest' => $directory . '/nested-repeater-fixture-' . wp_generate_uuid4() . '.json',
            ];
            elemacy_test_assert((bool) file_put_contents($fixture['manifest'], wp_json_encode($fixture)), 'Cannot record fixture ownership.');
            chmod($fixture['manifest'], 0600);
            echo wp_json_encode($fixture) . "\n";
        } catch (Throwable $error) {
            foreach (array_reverse($owned_posts) as $id) {
                wp_delete_post($id, true);
            }
            $documents->cleanup();
            if ($group_id) {
                acf_delete_field_group($group_id);
            }
            false === $previous_cache_ttl ? delete_option('elementor_element_cache_ttl') : update_option('elementor_element_cache_ttl', $previous_cache_ttl);
            throw $error;
        }
    } elseif ($command === 'cleanup') {
        [$path, $fixture] = elemacy_nr_manifest($directory, $argv[2] ?? '');
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
        if (!empty($fixture['field_group']) && function_exists('acf_delete_field_group')) {
            acf_delete_field_group((int) $fixture['field_group']);
        }
        $previous = $fixture['previous_cache_ttl'] ?? false;
        false === $previous ? delete_option('elementor_element_cache_ttl') : update_option('elementor_element_cache_ttl', $previous);
        unlink($path);
        echo "Nested repeater browser fixture removed.\n";
    } else {
        throw new InvalidArgumentException('Use create or cleanup <manifest>.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Nested repeater browser fixture failed: ' . $error->getMessage() . "\n");
    exit(1);
}
