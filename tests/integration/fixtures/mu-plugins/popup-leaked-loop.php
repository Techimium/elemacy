<?php

/**
 * Another plugin's loop that never calls wp_reset_postdata(), run on `wp`
 * (before wp_enqueue_scripts) for the request `?elemacy_leaked_loop=1`: the
 * global post is left on the last post of the list in the
 * `elemacy_fixture_leaked_loop_ids` option, not on the page being viewed.
 */
defined('ELEMACY_INTEGRATION_TOKEN') || exit;

add_action('wp', static function (): void {
    if (empty($_GET['elemacy_leaked_loop'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- test fixture, request flag only.
        return;
    }

    $ids = array_map('intval', (array) get_option('elemacy_fixture_leaked_loop_ids', []));
    if (!$ids) {
        return;
    }

    $loop = new WP_Query(['post__in' => $ids, 'orderby' => 'post__in', 'posts_per_page' => count($ids), 'ignore_sticky_posts' => true]);
    while ($loop->have_posts()) {
        $loop->the_post();
    }
    // Deliberately no wp_reset_postdata().
}, 99);
