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

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery
// Delete every library post and its meta in one query rather than loading and
// deleting each post individually. (Elementor's generated per-post CSS files are
// left on disk — an acceptable trade on uninstall.)
$wpdb->query(
    $wpdb->prepare(
        "DELETE p, pm FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
         WHERE p.post_type = %s",
        'elemacy_library'
    )
);

$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s",
        $wpdb->esc_like('elemacy_') . '%',
        $wpdb->esc_like('elemacy_pro_') . '%'
    )
);

$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options}
         WHERE (option_name LIKE %s OR option_name LIKE %s)
         AND option_name NOT LIKE %s AND option_name NOT LIKE %s",
        $wpdb->esc_like('_transient_elemacy_') . '%',
        $wpdb->esc_like('_transient_timeout_elemacy_') . '%',
        $wpdb->esc_like('_transient_elemacy_pro_') . '%',
        $wpdb->esc_like('_transient_timeout_elemacy_pro_') . '%'
    )
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery
