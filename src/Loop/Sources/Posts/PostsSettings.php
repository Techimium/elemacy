<?php

namespace Elemacy\Loop\Sources\Posts;

defined('ABSPATH') || exit;

use Elemacy\Loop\Access\PublicLoopLimits;

/**
 * The Posts source's setting names and defaults, written once. The names are
 * saved in sites' Elementor data, and Elementor stores nothing for a control
 * left at its default, so neither ever changes (loop-control-defaults.php
 * pins both). The control, the query and the public policy all read them
 * from here, so page 1 and page 2 can never disagree.
 *
 * @internal
 */
final class PostsSettings
{
    public const POST_TYPE = 'post_type';
    public const PER_PAGE = 'posts_per_page';
    public const OFFSET = 'offset';
    public const ORDER_BY = 'orderby';
    public const ORDER = 'order';
    public const EXCLUDE_CURRENT = 'exclude_current_post';
    public const INCLUDE_TERMS = 'posts_include_terms';
    public const INCLUDE_AUTHORS = 'posts_include_authors';
    public const EXCLUDE_POSTS = 'posts_exclude_posts';
    public const EXCLUDE_TERMS = 'posts_exclude_terms';
    public const EXCLUDE_AUTHORS = 'posts_exclude_authors';
    public const DATE_RANGE = 'posts_date_range';
    public const DATE_AFTER = 'posts_date_after';
    public const DATE_BEFORE = 'posts_date_before';
    public const META_CONDITIONS = 'posts_meta_conditions';
    public const ORDER_META_KEY = 'posts_order_meta_key';
    public const IGNORE_STICKY = 'posts_ignore_sticky';
    public const AVOID_DUPLICATES = 'posts_avoid_duplicates';
    public const QUERY_ID = 'posts_query_id';

    public const DEFAULT_POST_TYPE = 'post';
    public const DEFAULT_PER_PAGE = 6;
    public const DEFAULT_OFFSET = 0;
    public const DEFAULT_ORDER_BY = 'date';
    public const DEFAULT_ORDER = 'DESC';
    public const DEFAULT_EXCLUDE_CURRENT = 'yes';

    /** Every value the Order By control offers. */
    public const ORDER_BY_CHOICES = ['date', 'modified', 'title', 'author', 'comment_count', 'menu_order', 'rand', 'id', 'meta_value', 'meta_value_num'];

    /** The Order By choices that sort by the custom field named in ORDER_META_KEY. */
    public const META_ORDERS = ['meta_value', 'meta_value_num'];

    /** Not controls: what the public policy adds for the query. */
    public const CURRENT_POST_ID = 'current_post_id';
    public const OFFSET_BASE = 'offset_override_base';
    public const PUBLIC_QUERY = '_elemacy_public_query';

    /**
     * The page size every post source runs with, whatever was saved: a whole
     * number from 1 to 100, and the default for an empty, missing, zero,
     * negative or non-numeric value. The editor preview, the first page and
     * the pagination endpoint all read it here.
     *
     * @param mixed $value
     */
    public static function page_size($value): int
    {
        if (!is_numeric($value) || is_nan((float) $value) || (float) $value < 1) {
            return self::DEFAULT_PER_PAGE;
        }

        return (float) $value >= PublicLoopLimits::MAX_ITEMS ? PublicLoopLimits::MAX_ITEMS : (int) $value;
    }

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
            self::DATE_RANGE => '',
            self::DATE_AFTER => '',
            self::DATE_BEFORE => '',
            self::ORDER_META_KEY => '',
            self::IGNORE_STICKY => '',
            self::AVOID_DUPLICATES => '',
            self::QUERY_ID => '',
        ];
    }
}
