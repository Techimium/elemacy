<?php

namespace Elemacy\TemplateLibrary;

defined('ABSPATH') || exit;

/**
 * The single CPT that backs every template-like item — theme templates, popups,
 * and anything modules add later. Registered by Core regardless of which modules
 * are active, so disabling a module never orphans its content and there is one
 * permalink structure to flush.
 */
class LibraryPostType
{
    const POST_TYPE = 'elemacy_library';

    public static function register(): void
    {
        add_action('init', [static::class, 'register_post_type']);
    }

    public static function register_post_type(): void
    {
        register_post_type(static::POST_TYPE, static::get_args());
    }

    protected static function get_args(): array
    {
        $labels = [
            'name'               => _x('Library', 'post type general name', 'elemacy'),
            'singular_name'      => _x('Library Item', 'post type singular name', 'elemacy'),
            'menu_name'          => _x('Library', 'admin menu', 'elemacy'),
            'name_admin_bar'     => _x('Library Item', 'add new on admin bar', 'elemacy'),
            'add_new'            => _x('Add New', 'library item', 'elemacy'),
            'add_new_item'       => __('Add New Library Item', 'elemacy'),
            'new_item'           => __('New Library Item', 'elemacy'),
            'edit_item'          => __('Edit Library Item', 'elemacy'),
            'view_item'          => __('View Library Item', 'elemacy'),
            'all_items'          => __('All Library Items', 'elemacy'),
            'search_items'       => __('Search Library', 'elemacy'),
            'parent_item_colon'  => __('Parent Items:', 'elemacy'),
            'not_found'          => __('No library items found.', 'elemacy'),
            'not_found_in_trash' => __('No library items found in Trash.', 'elemacy'),
        ];

        return [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => true,
            'show_ui'            => true,
            // The React admin app is the canonical UI; no standalone WP menu.
            'show_in_menu'       => false,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'elemacy-library'],
            'capability_type'    => 'post',
            // Library items render site-wide (templates/popups), so every write
            // path — wp-admin, core REST, plugin REST — must be admin-only.
            'map_meta_cap'       => true,
            'capabilities'       => [
                'edit_posts'             => 'manage_options',
                'edit_others_posts'      => 'manage_options',
                'publish_posts'          => 'manage_options',
                'delete_posts'           => 'manage_options',
                'delete_others_posts'    => 'manage_options',
                'delete_published_posts' => 'manage_options',
                'edit_published_posts'   => 'manage_options',
                'read_private_posts'     => 'manage_options',
                'create_posts'           => 'manage_options',
            ],
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => ['title', 'editor', 'author', 'thumbnail', 'elementor'],
            // No core wp/v2/elemacy_library controller: the plugin's own
            // /elemacy/library REST routes (Route::*) are registered
            // independently and unaffected by this flag. Elementor's editor
            // reaches posts via post.php, not core REST, so nothing needs it.
            'show_in_rest'       => false,
        ];
    }
}
