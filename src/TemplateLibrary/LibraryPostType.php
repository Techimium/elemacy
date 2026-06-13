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
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => ['title', 'editor', 'author', 'thumbnail', 'elementor'],
            'show_in_rest'       => true,
        ];
    }
}
