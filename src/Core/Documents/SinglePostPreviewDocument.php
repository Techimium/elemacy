<?php

namespace Elemacy\Core\Documents;

defined('ABSPATH') || exit;

use Elemacy\Support\PostTypes;
use Elemacy\Core\Constants\PostStatus;
use Elemacy\Core\Controls\Query\QueryControl;
use Elemacy\Core\Controls\Query\QueryService;

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
                'label'        => esc_html__('Preview Content', 'elemacy'),
                'type'         => QueryControl::TYPE,
                'label_block'  => true,
                'placeholder'  => esc_html__('Auto (most recent post)', 'elemacy'),
                'autocomplete' => [
                    'object' => QueryService::OBJECT_POST,
                    'query'  => ['post_type' => PostTypes::public_names()],
                ],
                'default'      => '',
                'description'  => esc_html__('Search for a real post to preview this template with. Defaults to the most recent post.', 'elemacy'),
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

    protected function most_recent_post_id(): int
    {
        $post_types = PostTypes::public_names();

        if (empty($post_types)) {
            return 0;
        }

        $ids = get_posts([
            'post_type'        => $post_types,
            'post_status'      => PostStatus::PUBLISH,
            'posts_per_page'   => 1,
            'orderby'          => 'date',
            'order'            => 'DESC',
            'fields'           => 'ids',
            'no_found_rows'    => true,
            'suppress_filters' => false,
        ]);

        return $ids ? (int) $ids[0] : 0;
    }
}
