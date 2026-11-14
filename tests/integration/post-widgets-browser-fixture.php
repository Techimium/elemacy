<?php

/**
 * Real WordPress/Elementor fixture for the Post Grid and Post Carousel browser
 * journey (tests/e2e/run-post-widgets.mjs): a custom post type with images and
 * terms, and one published page per scenario. Owns only what it records.
 */
try {
    require __DIR__ . '/bootstrap.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $directory = dirname($elemacy_test_environment['root']);
    $command = $argv[1] ?? '';

    function elemacy_pw_manifest(string $directory, string $path): array
    {
        $real = realpath($path);
        elemacy_test_assert($real && dirname($real) === $directory
            && preg_match('/\Apost-widgets-fixture-[a-f0-9-]+\.json\z/', basename($real)), 'Refusing an unrelated fixture manifest.');
        $fixture = json_decode(file_get_contents($real), true);
        elemacy_test_assert(($fixture['token'] ?? '') === ELEMACY_INTEGRATION_TOKEN, 'Fixture belongs to another environment.');

        return [$real, $fixture];
    }

    function elemacy_pw_image(): int
    {
        $uploads = wp_upload_dir();
        $path = trailingslashit($uploads['path']) . 'elemacy-fx-' . wp_generate_uuid4() . '.png';
        $image = imagecreatetruecolor(800, 500);
        imagefill($image, 0, 0, imagecolorallocate($image, 40, 120, 200));
        elemacy_test_assert(imagepng($image, $path), 'Could not write the fixture image.');
        $id = wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => 'Fixture cover', 'post_status' => 'inherit'], $path);
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $path));
        update_post_meta($id, '_wp_attachment_image_alt', 'Fixture cover');

        return $id;
    }

    function elemacy_pw_page(string $title, array $widgets): int
    {
        $elements = [];
        foreach ($widgets as $index => [$id, $type, $settings]) {
            $elements[] = [
                'id' => 'fxc' . $index . 'c0', 'elType' => 'container', 'settings' => [],
                'elements' => [['id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => []]],
            ];
        }
        $document = Elementor\Plugin::instance()->documents->create('wp-page', ['post_type' => 'page', 'post_title' => $title, 'post_status' => 'draft']);
        elemacy_test_assert(!is_wp_error($document), 'Could not create a fixture page.');
        elemacy_test_assert((bool) $document->save(['elements' => $elements, 'settings' => ['post_status' => 'publish']]), 'Could not save a fixture page.');

        return (int) $document->get_main_id();
    }

    if ($command === 'create') {
        $administrator = get_user_by('login', 'elemacy_test_admin');
        wp_set_current_user($administrator->ID);
        wp_set_password('pw-' . ELEMACY_INTEGRATION_TOKEN, $administrator->ID);

        update_option('elemacy_fx_post_widgets', '1');
        elemacy_fx_register_book_type();

        $genre = wp_insert_term('Fixture genre ' . wp_generate_uuid4(), 'elemacy_fx_genre');
        elemacy_test_assert(is_array($genre), 'Could not create the fixture term.');
        $image = elemacy_pw_image();
        $books = [];
        foreach ([20, 200, 60, 320, 30, 140] as $index => $words) {
            $id = wp_insert_post([
                'post_type' => 'elemacy_fx_book', 'post_status' => 'publish', 'post_title' => 'Fixture book ' . ($index + 1),
                'post_content' => trim(str_repeat('word ', $words)), 'post_date' => '2024-01-0' . (9 - $index) . ' 10:00:00',
            ], true);
            elemacy_test_assert(is_int($id), 'Could not create a fixture book.');
            set_post_thumbnail($id, $image);
            update_post_meta($id, 'fx_code', 'Code ' . ($index + 1));
            wp_set_object_terms($id, [(int) $genre['term_id']], 'elemacy_fx_genre');
            $books[] = $id;
        }

        $query = ['data_source' => 'posts', 'post_type' => 'elemacy_fx_book', 'exclude_current_post' => '', 'orderby' => 'date', 'order' => 'DESC'];
        $card = [
            ['_id' => 'fxrow01', 'part' => 'image', 'zone' => 'body'],
            ['_id' => 'fxrow02', 'part' => 'terms', 'zone' => 'media_top'],
            ['_id' => 'fxrow03', 'part' => 'title', 'zone' => 'body'],
            ['_id' => 'fxrow04', 'part' => 'excerpt', 'zone' => 'body', 'excerpt_length' => 100],
            ['_id' => 'fxrow05', 'part' => 'button', 'zone' => 'footer'],
        ];
        $positions = [];
        foreach (['top', 'left', 'right', 'background'] as $position) {
            $positions[] = ['fx' . substr($position, 0, 3) . 'p1', 'elemacy-post-grid', $query + [
                'posts_per_page' => 1, 'columns' => '1', 'columns_tablet' => '1', 'columns_mobile' => '1', 'card_image_position' => $position,
                'card_image_position_mobile' => 'left' === $position ? 'top' : $position, 'card_parts' => $card,
                'card_min_height' => ['unit' => 'px', 'size' => 300],
            ]];
        }

        $pages = [
            'positions' => elemacy_pw_page('Fx positions', $positions),
            'grid' => elemacy_pw_page('Fx grid', [['fxgrid1', 'elemacy-post-grid', $query + ['posts_per_page' => 2, 'pagination_type' => 'load_more', 'columns' => '2', 'card_parts' => $card]]]),
            'masonry' => elemacy_pw_page('Fx masonry', [['fxmas01', 'elemacy-post-grid', $query + ['posts_per_page' => 6, 'layout' => 'masonry', 'columns' => '3', 'card_parts' => $card]]]),
            'masonry_more' => elemacy_pw_page('Fx masonry more', [['fxmas02', 'elemacy-post-grid', $query + ['posts_per_page' => 3, 'layout' => 'masonry', 'columns' => '3', 'pagination_type' => 'load_more', 'card_parts' => $card]]]),
            'carousel' => elemacy_pw_page('Fx carousel', [['fxcar01', 'elemacy-post-carousel', $query + ['posts_per_page' => 6, 'slides_per_view' => '2', 'autoplay' => '', 'card_parts' => $card]]]),
            'linked' => elemacy_pw_page('Fx linked', [['fxlnk01', 'elemacy-post-grid', $query + ['posts_per_page' => 1, 'columns' => '1', 'card_whole_link' => 'yes', 'card_parts' => $card]], ['fxlnk02', 'elemacy-post-grid', $query + ['posts_per_page' => 1, 'columns' => '1', 'card_whole_link' => 'yes', 'card_image_position' => 'background', 'card_min_height' => ['unit' => 'px', 'size' => 320], 'card_parts' => $card]]]),
            'editor' => elemacy_pw_page('Fx editor', [['fxedt01', 'elemacy-post-grid', $query + ['posts_per_page' => 2]]]),
        ];

        $urls = [];
        foreach ($pages as $key => $id) {
            $urls[$key] = get_permalink($id);
        }

        $fixture = [
            'token' => ELEMACY_INTEGRATION_TOKEN,
            'manifest' => $directory . '/post-widgets-fixture-' . wp_generate_uuid4() . '.json',
            'pages' => $pages,
            'urls' => $urls,
            'editor_url' => admin_url('post.php?post=' . $pages['editor'] . '&action=elementor'),
            'books' => $books,
            'image' => $image,
            'genre' => (int) $genre['term_id'],
            'genre_url' => get_term_link((int) $genre['term_id'], 'elemacy_fx_genre'),
        ];
        elemacy_test_assert((bool) file_put_contents($fixture['manifest'], wp_json_encode($fixture)), 'Cannot record the fixture.');
        chmod($fixture['manifest'], 0600);
        echo wp_json_encode($fixture);
    } elseif ($command === 'cleanup') {
        [$path, $fixture] = elemacy_pw_manifest($directory, $argv[2] ?? '');
        elemacy_fx_register_book_type();
        foreach (array_merge(array_values($fixture['pages']), $fixture['books']) as $id) {
            wp_delete_post((int) $id, true);
        }
        wp_delete_attachment((int) $fixture['image'], true);
        wp_delete_term((int) $fixture['genre'], 'elemacy_fx_genre');
        delete_option('elemacy_fx_post_widgets');
        unlink($path);
    } else {
        throw new InvalidArgumentException('Use create or cleanup <manifest>.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
