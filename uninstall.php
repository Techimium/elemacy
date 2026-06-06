<?php

/**
 * Removes all Elemacy data when the plugin is deleted.
 *
 * Pro keys (elemacy_pro_*) are left untouched here — Elemacy Pro ships its own
 * uninstall handler so each plugin owns its cleanup.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$elemacy_post_types = ['elemacy_template', 'elemacy_popup'];

foreach ($elemacy_post_types as $elemacy_post_type) {
    $post_ids = get_posts([
        'post_type'   => $elemacy_post_type,
        'post_status' => 'any',
        'numberposts' => -1,
        'fields'      => 'ids',
    ]);

    foreach ($post_ids as $elemacy_post_id) {
        wp_delete_post($elemacy_post_id, true);
    }
}

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s",
        $wpdb->esc_like('elemacy_') . '%',
        $wpdb->esc_like('elemacy_pro_') . '%'
    )
);

$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('_transient_elemacy_') . '%',
        $wpdb->esc_like('_transient_timeout_elemacy_') . '%'
    )
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery
