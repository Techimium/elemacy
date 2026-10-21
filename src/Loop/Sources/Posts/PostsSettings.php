<?php

namespace Elemacy\Loop\Sources\Posts;

defined('ABSPATH') || exit;

/**
 * The Posts source's setting names and defaults, written once. The names are
 * saved in sites' Elementor data, and Elementor stores nothing for a control
 * left at its default, so neither ever changes (loop-control-defaults.php
 * pins both). The control, the query and the public policy all read them
 * from here, so page 1 and page 2 can never disagree.
 */
final class PostsSettings
{
    public const POST_TYPE = 'post_type';
    public const PER_PAGE = 'posts_per_page';
    public const OFFSET = 'offset';
    public const ORDER_BY = 'orderby';
    public const ORDER = 'order';
    public const EXCLUDE_CURRENT = 'exclude_current_post';

    public const DEFAULT_POST_TYPE = 'post';
    public const DEFAULT_PER_PAGE = 6;
    public const DEFAULT_OFFSET = 0;
    public const DEFAULT_ORDER_BY = 'date';
    public const DEFAULT_ORDER = 'DESC';
    public const DEFAULT_EXCLUDE_CURRENT = 'yes';

    /** Not controls: what the public policy adds for the query. */
    public const CURRENT_POST_ID = 'current_post_id';
    public const OFFSET_BASE = 'offset_override_base';
    public const PUBLIC_QUERY = '_elemacy_public_query';

    /** @return array<string, mixed> Each control's default, keyed by its setting name. */
    public static function defaults(): array
    {
        return [
            self::POST_TYPE => self::DEFAULT_POST_TYPE,
            self::PER_PAGE => self::DEFAULT_PER_PAGE,
            self::OFFSET => self::DEFAULT_OFFSET,
            self::ORDER_BY => self::DEFAULT_ORDER_BY,
            self::ORDER => self::DEFAULT_ORDER,
            self::EXCLUDE_CURRENT => self::DEFAULT_EXCLUDE_CURRENT,
        ];
    }
}
