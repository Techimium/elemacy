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
// Delete each library post's Elementor-generated CSS file before the posts
// themselves are removed below — otherwise these files are orphaned on disk
// with no post left to regenerate or ever clean them up.
$library_post_ids = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
        'elemacy_library'
    )
);

if (!empty($library_post_ids)) {
    // Prefer Elementor's own file class so this stays correct even if a future
    // Elementor version changes the storage path/convention. Instantiated
    // directly (not via Post::create(), which reaches into Plugin::$instance)
    // so there is no dependency on Elementor's own bootstrap having run.
    $can_use_elementor_api = class_exists('\Elementor\Core\Files\CSS\Post');

    // Fallback only: Elementor's own post-CSS convention as of this writing —
    // {uploads}/elementor/css/post-{ID}.css — used when Elementor isn't
    // active/loaded, or its file class unexpectedly errors.
    $fallback_css_dir = trailingslashit(wp_upload_dir()['basedir']) . 'elementor/css/';

    foreach ($library_post_ids as $library_post_id) {
        $library_post_id = (int) $library_post_id;
        $deleted_via_elementor = false;

        if ($can_use_elementor_api) {
            try {
                (new \Elementor\Core\Files\CSS\Post($library_post_id))->delete();
                $deleted_via_elementor = true;
            } catch (\Throwable $exception) {
                $deleted_via_elementor = false;
            }
        }

        if (!$deleted_via_elementor) {
            $css_file = $fallback_css_dir . 'post-' . $library_post_id . '.css';

            if (file_exists($css_file)) {
                wp_delete_file($css_file);
            }
        }
    }
}

// Delete every library post and its meta in one query rather than loading and
// deleting each post individually.
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
