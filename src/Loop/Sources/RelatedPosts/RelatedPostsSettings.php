<?php

namespace Elemacy\Loop\Sources\RelatedPosts;

defined('ABSPATH') || exit;

use Elemacy\Loop\Sources\Posts\PostsSettings;

/**
 * The Related Posts source's setting names and defaults, written once for its
 * control, its query and its public policy.
 *
 * @internal
 */
final class RelatedPostsSettings
{
    public const TAXONOMIES = 'related_posts_taxonomies';
    public const ORDER_BY = 'related_posts_orderby';
    public const ORDER = 'related_posts_order';
    public const FALLBACK = 'related_posts_fallback';

    /** The widget's shared page size: see SharedPageSizeSourceInterface. */
    public const PER_PAGE = PostsSettings::PER_PAGE;

    public const DEFAULT_PER_PAGE = PostsSettings::DEFAULT_PER_PAGE;
    public const DEFAULT_ORDER_BY = 'date';
    public const DEFAULT_ORDER = 'DESC';
    public const DEFAULT_FALLBACK = '';

    public const ORDER_BY_CHOICES = ['date', 'modified', 'title', 'comment_count', 'menu_order', 'rand'];

    /** Not controls: what the public policy adds for the query. */
    public const CURRENT_POST_ID = 'current_post_id';
    public const PUBLIC_QUERY = '_elemacy_public_query';

    /** @return array<string, mixed> Each scalar control's default, keyed by its setting name. */
    public static function defaults(): array
    {
        return [
            self::PER_PAGE => self::DEFAULT_PER_PAGE,
            self::ORDER_BY => self::DEFAULT_ORDER_BY,
            self::ORDER => self::DEFAULT_ORDER,
            self::FALLBACK => self::DEFAULT_FALLBACK,
        ];
    }
}
