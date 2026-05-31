<?php

namespace Elemacy\Modules\Popups\PostTypes;

defined('ABSPATH') || exit;

class PopupPostType
{
    const POST_TYPE = 'elemacy_popup';

    public static function register()
    {
        static::register_post_type();
    }

    protected static function register_post_type()
    {
        add_action('init', function () {
            $labels = [
                'name' => _x('Popups', 'post type general name', 'elemacy'),
                'singular_name' => _x('Popup', 'post type singular name', 'elemacy'),
                'menu_name' => _x('Popups', 'admin menu', 'elemacy'),
                'name_admin_bar' => _x('Popup', 'add new on admin bar', 'elemacy'),
                'add_new' => _x('Add New', 'popup', 'elemacy'),
                'add_new_item' => __('Add New Popup', 'elemacy'),
                'new_item' => __('New Popup', 'elemacy'),
                'edit_item' => __('Edit Popup', 'elemacy'),
                'view_item' => __('View Popup', 'elemacy'),
                'all_items' => __('All Popups', 'elemacy'),
                'search_items' => __('Search Popups', 'elemacy'),
                'parent_item_colon' => __('Parent Popups:', 'elemacy'),
                'not_found' => __('No popups found.', 'elemacy'),
                'not_found_in_trash' => __('No popups found in Trash.', 'elemacy')
            ];

            $args = [
                'labels' => $labels,
                'public' => false,
                'publicly_queryable' => true,
                'show_ui' => true,
                'show_in_menu' => false,
                'query_var' => true,
                'rewrite' => ['slug' => 'elemacy-popup'],
                'capability_type' => 'post',
                'has_archive' => false,
                'hierarchical' => false,
                'menu_position' => null,
                'supports' => ['title', 'editor', 'author', 'elementor'],
                'show_in_rest' => true,
            ];

            register_post_type(static::POST_TYPE, $args);

            static::maybe_flush_rewrite_rules(); // @todo: optimize later
        });
    }

    /**
     * Flush rewrite rules once after the popup CPT is first registered.
     *
     * The module ships disabled and is enabled from the admin UI long after the
     * plugin was activated, so the CPT's rewrite rules are not part of the
     * flushed set. Without them the popup permalink 404s — which breaks the
     * Elementor editor preview ("You must call 'the_content' function…"). A
     * guarded, versioned one-time flush adds the rules without the cost of
     * flushing on every request.
     */
    protected static function maybe_flush_rewrite_rules(): void
    {
        $flag = 'elemacy_popup_rewrite_flushed_v1';

        if (get_option($flag) !== 'yes') {
            flush_rewrite_rules(false);
            update_option($flag, 'yes');
        }
    }
}
