<?php

namespace Elemacy\Loop\Pagination;

defined('ABSPATH') || exit;

/**
 * The saved `pagination_type` values of a Loop Grid. The three link types
 * render page links; the two incremental types append the next page's items
 * to the loop instead, and are always loaded in place.
 */
final class PaginationType
{
    public const NUMBERS = 'numbers';
    public const PREV_NEXT = 'prev_next';
    public const NUMBERS_AND_PREV_NEXT = 'numbers_and_prev_next';
    public const LOAD_MORE = 'load_more';
    public const INFINITE_SCROLL = 'infinite_scroll';

    /** @var string[] Types that render page links and replace the loop. */
    public const LINKS = [self::NUMBERS, self::PREV_NEXT, self::NUMBERS_AND_PREV_NEXT];

    /** @var string[] Types that append the next page to the loop. */
    public const INCREMENTAL = [self::LOAD_MORE, self::INFINITE_SCROLL];

    public static function is_incremental($type): bool
    {
        return in_array($type, self::INCREMENTAL, true);
    }
}
