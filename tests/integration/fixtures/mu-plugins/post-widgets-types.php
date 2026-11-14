<?php

/** Only copied into a marked disposable environment, never installed on development. */
defined('ELEMACY_INTEGRATION_TOKEN') || exit;

/** A custom post type every request of the browser journey can see. */
function elemacy_fx_register_book_type(): void
{
    register_post_type('elemacy_fx_book', [
        'public' => true,
        'label' => 'Fixture Books',
        'supports' => ['title', 'editor', 'thumbnail'],
        'rewrite' => false,
    ]);
    register_taxonomy('elemacy_fx_genre', 'elemacy_fx_book', ['public' => true, 'label' => 'Fixture Genres', 'rewrite' => false]);
}

add_action('init', static function (): void {
    if (get_option('elemacy_fx_post_widgets')) {
        elemacy_fx_register_book_type();
    }
});
