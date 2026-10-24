<?php

namespace Elemacy\Loop\Sources\Terms;

defined('ABSPATH') || exit;

/**
 * The Taxonomy Terms source's setting names and defaults, written once. The
 * names are saved in sites' Elementor data, and Elementor stores nothing for
 * a control left at its default, so neither ever changes
 * (loop-control-defaults.php pins both). The control, the query and the
 * public policy all read them from here.
 */
final class TermsSettings
{
    public const TAXONOMY = 'taxonomy';
    public const NUMBER = 'terms_number';
    public const ORDER_BY = 'terms_orderby';
    public const ORDER = 'terms_order';
    public const HIDE_EMPTY = 'hide_empty';
    public const EXCLUDE_CURRENT = 'exclude_current_term';

    public const DEFAULT_TAXONOMY = 'category';
    public const DEFAULT_NUMBER = 6;
    public const DEFAULT_ORDER_BY = 'name';
    public const DEFAULT_ORDER = 'ASC';
    public const DEFAULT_HIDE_EMPTY = 'yes';
    public const DEFAULT_EXCLUDE_CURRENT = 'yes';

    /** Not a control: the current term the public policy adds for the query. */
    public const CURRENT_TERM_ID = 'current_term_id';

    /** @return array<string, mixed> Each control's default, keyed by its setting name. */
    public static function defaults(): array
    {
        return [
            self::TAXONOMY => self::DEFAULT_TAXONOMY,
            self::NUMBER => self::DEFAULT_NUMBER,
            self::ORDER_BY => self::DEFAULT_ORDER_BY,
            self::ORDER => self::DEFAULT_ORDER,
            self::HIDE_EMPTY => self::DEFAULT_HIDE_EMPTY,
            self::EXCLUDE_CURRENT => self::DEFAULT_EXCLUDE_CURRENT,
        ];
    }
}
