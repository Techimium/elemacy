<?php

// A theme's own assets, which must survive its chrome being discarded.
add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style('generic-fixture-style', get_stylesheet_uri(), [], '1.0.0');
    wp_enqueue_script('generic-fixture-script', get_template_directory_uri() . '/fixture.js', [], '1.0.0', true);
});
