<?php

namespace Elemacy\Loop\Sources\ManualPosts;

defined('ABSPATH') || exit;

use Elemacy\Loop\Sources\Posts\PostsSettings;

/**
 * The Manual Selection source's setting names and defaults, written once for
 * its control, its query and its public policy.
 *
 * @internal
 */
final class ManualPostsSettings
{
    public const IDS = 'manual_posts_ids';

    /** The widget's shared page size: see SharedPageSizeSourceInterface. */
    public const PER_PAGE = PostsSettings::PER_PAGE;

    /** Not a control: what the public policy adds for the query. */
    public const PUBLIC_QUERY = '_elemacy_public_query';
}
