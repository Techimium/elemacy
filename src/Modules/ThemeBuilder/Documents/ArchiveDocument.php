<?php

namespace Elemacy\Modules\ThemeBuilder\Documents;

defined('ABSPATH') || exit;

use Elemacy\Core\Documents\PreviewablePageBase;
use Elementor\Controls_Manager;
use WP_Post_Type;

/**
 * Elementor document for Archive templates. An archive renders a list of posts, so
 * it previews against a real query — the user picks a post type and the preview
 * resolves the archive title, queried object and post loop for that type, like
 * Elementor Pro's theme builder.
 */
class ArchiveDocument extends PreviewablePageBase
{
    const PREVIEW_POST_TYPE = 'elemacy_preview_post_type';

    public static function get_type()
    {
        return 'elemacy_archive';
    }

    public function get_name()
    {
        return 'elemacy_archive';
    }

    public static function get_title()
    {
        return esc_html__('Archive', 'elemacy');
    }

    public static function get_plural_title()
    {
        return esc_html__('Archives', 'elemacy');
    }

    public static function get_properties()
    {
        $properties = parent::get_properties();

        $properties['support_kit']     = true;
        $properties['show_in_library'] = false;
        $properties['admin_tab_group'] = '';

        return $properties;
    }

    protected function register_preview_controls(): void
    {
        $this->add_control(
            self::PREVIEW_POST_TYPE,
            [
                'label'       => esc_html__('Preview Source', 'elemacy'),
                'type'        => Controls_Manager::SELECT2,
                'label_block' => true,
                'options'     => $this->post_type_options(),
                'default'     => 'post',
                'description' => esc_html__('Choose a post type to preview this archive with.', 'elemacy'),
            ]
        );
    }

    protected function preview_query_args(): array
    {
        $post_type = (string) $this->get_settings(self::PREVIEW_POST_TYPE);

        if ('' === $post_type || !post_type_exists($post_type)) {
            $post_type = 'post';
        }

        return [
            'post_type'           => $post_type,
            'posts_per_page'      => (int) get_option('posts_per_page'),
            'ignore_sticky_posts' => true,
        ];
    }

    /**
     * Public, archive-capable post types as SELECT2 options. Built in-memory, so
     * no DB query and no editor-only gate is needed.
     *
     * @return array<string, string>
     */
    protected function post_type_options(): array
    {
        $options = [];

        foreach (get_post_types(['public' => true], 'objects') as $post_type) {
            if (!$post_type instanceof WP_Post_Type || 'attachment' === $post_type->name) {
                continue;
            }

            $options[$post_type->name] = $post_type->labels->name;
        }

        return $options;
    }
}
