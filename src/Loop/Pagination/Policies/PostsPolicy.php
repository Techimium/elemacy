<?php

namespace Elemacy\Loop\Pagination\Policies;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Loop\Sources\PostsQuery;
use Elemacy\Loop\Pagination\PublicLoopLimits;

/**
 * The Posts source's public-context policy, kept free of any Elementor
 * widget/document dependency; PostsDataSource delegates get_public_settings()
 * to an instance of this class.
 */
class PostsPolicy implements PublicLoopSourcePolicyInterface
{
    protected PostsQuery $query;

    public function __construct(?PostsQuery $query = null)
    {
        $this->query = $query ?? new PostsQuery();
    }

    /** {@inheritDoc} */
    public function get_public_settings(array $settings, array $context, int $page): array
    {
        PublicLoopLimits::assert_page($page);
        PublicLoopLimits::assert_scalars($settings, ['post_type', 'posts_per_page', 'offset', 'orderby', 'order', 'exclude_current_post']);
        $saved = $settings + [
            'post_type' => 'post',
            'posts_per_page' => 6,
            'exclude_current_post' => 'yes',
        ];
        $saved['posts_per_page'] = PublicLoopLimits::page_size($saved['posts_per_page']);
        $saved['current_post_id'] = (int) ($context['post_id'] ?? 0);

        $public = $this->query->sanitize_ajax_settings($saved, $page);
        $public['_elemacy_public_query'] = true;
        return $public;
    }
}
