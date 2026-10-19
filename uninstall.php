<?php
/**
 * Native, bounded cleanup of elemacy-owned data, run only when the site opts
 * in. Deactivation and a plain uninstall both keep the data.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/*
 * Deleting the plugin keeps the site's templates, popups and settings, as
 * Elementor does, so removing and reinstalling it (for instance to recover
 * from a failed update) loses nothing. To remove everything, add this to
 * wp-config.php before deleting the plugin:
 *
 *     define('ELEMACY_REMOVE_ALL_DATA', true);
 */
if (!defined('ELEMACY_REMOVE_ALL_DATA') || true !== ELEMACY_REMOVE_ALL_DATA) {
    return;
}

// Uninstall must work without either plugin's bootstrap or Composer autoloader.
require_once __DIR__ . '/src/Core/Multisite/SiteBatchIterator.php';

function elemacy_uninstall_current_site(): void
{
    global $wpdb;
    do {
        $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID ASC LIMIT 100", 'elemacy_library'));
        if ($wpdb->last_error) {
            throw new RuntimeException('Could not read Elemacy library cleanup batch.');
        }
        foreach ($ids as $id) {
            $id = (int) $id;
            // Keep Elementor's CSS cleanup when available, with its existing file fallback.
            $deleted_css = false;
            if (class_exists('\Elementor\Core\Files\CSS\Post')) {
                try {
                    (new \Elementor\Core\Files\CSS\Post($id))->delete();
                    $deleted_css = true;
                } catch (Throwable $error) {
                    $deleted_css = false;
                }
            }
            if (!$deleted_css) {
                $file = trailingslashit(wp_upload_dir()['basedir']) . 'elementor/css/post-' . $id . '.css';
                if (file_exists($file)) {
                    wp_delete_file($file);
                }
            }
            if (!wp_delete_post($id, true) && get_post($id)) {
                throw new RuntimeException('Native Elemacy document deletion failed.');
            }
        }
        $batch_full = count($ids) === 100;
    } while ($batch_full);

    $meta_keys = ['_elemacy_template_type', '_elemacy_conditions', '_elemacy_popup_triggers', '_elemacy_popup_rules'];
    foreach ($meta_keys as $key) {
        do {
            $ids = $wpdb->get_col($wpdb->prepare("SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_id ASC LIMIT 100", $key));
            if ($wpdb->last_error) {
                throw new RuntimeException('Could not read owned metadata cleanup batch.');
            }
            foreach ($ids as $id) {
                if (!delete_metadata_by_mid('post', (int) $id) && get_metadata_by_mid('post', (int) $id)) {
                    throw new RuntimeException('Owned metadata deletion failed.');
                }
            }
            $batch_full = count($ids) === 100;
        } while ($batch_full);
    }

    do {
        $names = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE (option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s) AND option_name NOT LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s ORDER BY option_name ASC LIMIT 100",
            $wpdb->esc_like('elemacy_') . '%',
            $wpdb->esc_like('_transient_elemacy_') . '%',
            $wpdb->esc_like('_transient_timeout_elemacy_') . '%',
            $wpdb->esc_like('elemacy_pro_') . '%',
            $wpdb->esc_like('_transient_elemacy_pro_') . '%',
            $wpdb->esc_like('_transient_timeout_elemacy_pro_') . '%'
        ));
        if ($wpdb->last_error) {
            throw new RuntimeException('Could not read owned option cleanup batch.');
        }
        foreach ($names as $name) {
            if (strpos($name, '_transient_timeout_') === 0) {
                delete_transient(substr($name, strlen('_transient_timeout_')));
            } elseif (strpos($name, '_transient_') === 0) {
                delete_transient(substr($name, strlen('_transient_')));
            }
            delete_option($name);
            if ($wpdb->get_var($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name = %s", $name)) !== null) {
                throw new RuntimeException('Owned option deletion failed.');
            }
        }
        $batch_full = count($names) === 100;
    } while ($batch_full);
}

function elemacy_uninstall_all_sites(): void
{
    if (!is_multisite()) {
        elemacy_uninstall_current_site();
        return;
    }
    $iterator = new \Elemacy\Core\Multisite\SiteBatchIterator();
    $offset = 0;
    $failures = 0;
    do {
        $batch = $iterator->process_batch(static function () use (&$failures): void {
            try {
                elemacy_uninstall_current_site();
            } catch (Throwable $error) {
                // Continue the network sweep; report failure after context is restored.
                ++$failures;
            }
        }, $offset, 100);
        $offset += $batch['processed'];
    } while ($batch['has_more']);
    delete_site_option('elemacy_network_seed_offset');
    if ($failures) {
        throw new RuntimeException('elemacy cleanup failed on ' . $failures . ' site(s); rerun cleanup before removal.'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The only interpolated value is a local integer count.
    }
}

elemacy_uninstall_all_sites();
