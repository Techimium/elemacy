<?php

namespace Elemacy\Loop\Sources\ManualPosts;

defined('ABSPATH') || exit;

use Elemacy\Loop\Access\PublicLoopLimits;
use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Loop\Sources\Posts\PostsFilters;
use Elemacy\Loop\Sources\Posts\PostsSettings;

/**
 * The Manual Selection source's public-context policy.
 *
 * @internal
 */
class ManualPostsPolicy implements PublicLoopSourcePolicyInterface
{
    /** {@inheritDoc} */
    public function get_public_settings(array $settings, array $context, int $page): array
    {
        PublicLoopLimits::assert_page($page);
        PublicLoopLimits::assert_scalars($settings, [ManualPostsSettings::PER_PAGE]);

        return [
            ManualPostsSettings::IDS => PostsFilters::id_list($settings[ManualPostsSettings::IDS] ?? []),
            ManualPostsSettings::PER_PAGE => PostsSettings::page_size($settings[ManualPostsSettings::PER_PAGE] ?? null),
            ManualPostsSettings::PUBLIC_QUERY => true,
            'paged' => $page,
        ];
    }
}
