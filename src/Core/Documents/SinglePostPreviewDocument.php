<?php

namespace Elemacy\Core\Documents;

defined('ABSPATH') || exit;

use Elemacy\Conditions\Support\PostTypes;
use Elemacy\Core\Constants\PostStatus;
use Elementor\Controls_Manager;
use Elementor\Plugin;
use WP_Post;

/**
 * Base for previewable documents that render in the context of a single real post
 * (Loop Item, Single template). Provides the shared "Preview Content" post picker
 * and resolves the chosen post; subclasses only declare their type/title/template.
 */
abstract class SinglePostPreviewDocument extends PreviewablePageBase
{
    const PREVIEW_POST = 'elemacy_preview_post';

    protected function register_preview_controls(): void
    {
        $this->add_control(
            self::PREVIEW_POST,
            [
                'label'       => esc_html__('Preview Content', 'elemacy'),
                'type'        => Controls_Manager::SELECT2,
                'label_block' => true,
                'options'     => $this->preview_post_options(),
                'default'     => '',
                'description' => esc_html__('Choose a real post to preview this template with. Defaults to the most recent post.', 'elemacy'),
            ]
        );
    }

    public function get_preview_post_id(): int
    {
        $selected = (int) $this->get_settings(self::PREVIEW_POST);

        if ($selected > 0 && PostStatus::PUBLISH === get_post_status($selected)) {
            return $selected;
        }

        return $this->most_recent_post_id();
    }

    /**
     * Recent published posts across public post types, labelled with their type,
     * as SELECT2 options. A single picker avoids a dependent post-type dropdown;
     * server-rendered, so no Pro-only query control is needed.
     *
     * @return array<int|string, string>
     */
    protected function preview_post_options(): array
    {
        $options = ['' => esc_html__('Auto (most recent post)', 'elemacy')];

        // The picker only renders in the editor panel; registering controls also
        // happens on every frontend render that builds this document, so skip the
        // post lookup unless we're actually in the editor.
        if (!Plugin::instance()->editor->is_edit_mode()) {
            return $options;
        }

        foreach ($this->recent_posts() as $post) {
            $post_type = get_post_type_object($post->post_type);
            $type_label = $post_type ? $post_type->labels->singular_name : $post->post_type;
            $title = '' !== $post->post_title ? $post->post_title : esc_html__('(no title)', 'elemacy');

            /* translators: 1: post title, 2: post type singular name. */
            $options[$post->ID] = sprintf(esc_html__('%1$s (%2$s)', 'elemacy'), $title, $type_label);
        }

        return $options;
    }

    protected function most_recent_post_id(): int
    {
        $posts = $this->recent_posts(1);

        return $posts ? (int) $posts[0]->ID : 0;
    }

    /**
     * @return WP_Post[]
     */
    protected function recent_posts(int $limit = 50): array
    {
        $post_types = array_map(
            static fn (array $post_type): string => $post_type['value'],
            PostTypes::as_sub_values()
        );

        if (empty($post_types)) {
            return [];
        }

        return get_posts([
            'post_type'        => $post_types,
            'post_status'      => PostStatus::PUBLISH,
            'posts_per_page'   => $limit,
            'orderby'          => 'date',
            'order'            => 'DESC',
            'suppress_filters' => false,
        ]);
    }
}
