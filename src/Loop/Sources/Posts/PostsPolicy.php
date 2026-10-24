<?php

namespace Elemacy\Loop\Sources\Posts;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Loop\Placement\HostFacts;
use Elemacy\Loop\Access\PublicLoopLimits;

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
        PublicLoopLimits::assert_scalars($settings, array_keys(PostsSettings::defaults()));
        $saved = $settings + PostsSettings::defaults();
        $saved[PostsSettings::PER_PAGE] = PublicLoopLimits::page_size($saved[PostsSettings::PER_PAGE]);
        $saved[PostsSettings::CURRENT_POST_ID] = (int) ($context[HostFacts::POST_ID] ?? 0);

        $public = $this->query->sanitize_ajax_settings($saved, $page);
        $public[PostsSettings::PUBLIC_QUERY] = true;
        return $public;
    }
}
