<?php

namespace Elemacy\Modules\ThemeBuilder\PostTypes;

defined('ABSPATH') || exit;

class TemplatePostType
{

    const POST_TYPE = 'elemacy_template';

    public static function register()
    {
        self::register_post_type();
        self::register_template_redirect();
    }

    protected static function register_post_type()
    {
        add_action('init', function () {
            $labels = [
                'name' => _x('Templates', 'post type general name', 'elemacy'),
                'singular_name' => _x('Template', 'post type singular name', 'elemacy'),
                'menu_name' => _x('Templates', 'admin menu', 'elemacy'),
                'name_admin_bar' => _x('Template', 'add new on admin bar', 'elemacy'),
                'add_new' => _x('Add New', 'template', 'elemacy'),
                'add_new_item' => __('Add New Template', 'elemacy'),
                'new_item' => __('New Template', 'elemacy'),
                'edit_item' => __('Edit Template', 'elemacy'),
                'view_item' => __('View Template', 'elemacy'),
                'all_items' => __('All Templates', 'elemacy'),
                'search_items' => __('Search Templates', 'elemacy'),
                'parent_item_colon' => __('Parent Templates:', 'elemacy'),
                'not_found' => __('No templates found.', 'elemacy'),
                'not_found_in_trash' => __('No templates found in Trash.', 'elemacy')
            ];

            $args = [
                'labels' => $labels,
                'public' => false,
                'publicly_queryable' => true,
                'show_ui' => true,
                'show_in_menu' => true,
                'query_var' => true,
                'rewrite' => ['slug' => 'elemacy-template'],
                'capability_type' => 'post',
                'has_archive' => false,
                'hierarchical' => false,
                'menu_position' => null,
                'supports' => ['title', 'editor', 'author', 'thumbnail', 'elementor'],
                'show_in_rest' => true,
            ];

            register_post_type(self::POST_TYPE, $args);
        });
    }

    public static function register_template_redirect()
    {
        add_filter('template_include', function ($template) {
            if (is_singular(static::POST_TYPE)) {
                return ELEMENTOR_PATH . '/modules/page-templates/templates/canvas.php';
            }

            return $template;
        });
    }
}
