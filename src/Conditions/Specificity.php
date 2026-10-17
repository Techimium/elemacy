<?php

namespace Elemacy\Conditions;

defined('ABSPATH') || exit;

/**
 * The ordered levels a display condition can target, from the whole site down
 * to one object. A template's specificity is the highest level among the
 * conditions that actually matched the request, and the most specific matching
 * template wins. See docs/ARCHITECTURE.md § Template precedence.
 *
 * Values are spaced by ten so a level can be inserted between two existing
 * ones without renumbering; only their order is meaningful.
 */
final class Specificity
{
    /** The entire site, with no further narrowing. */
    public const SITE = 10;

    /** A whole family of requests: every archive, or every singular document. */
    public const SECTION = 20;

    /** One kind of request: a post type, a taxonomy, search, the blog index. */
    public const TYPE = 30;

    /** One identified object (a specific post, term, user or the front page) or one narrowed kind of request (search restricted to a post type). */
    public const OBJECT = 40;

    /**
     * Every level, broadest first. Used by tests and diagnostics; resolution
     * itself only ever compares two integers.
     *
     * @return int[]
     */
    public static function levels(): array
    {
        return [self::SITE, self::SECTION, self::TYPE, self::OBJECT];
    }

    /**
     * The level a condition targeting $value resolves to: $narrow when the
     * rule carries a value that identifies something, $broad when it is empty
     * and the condition therefore matches its whole family.
     *
     * Every value-dependent condition narrows the same way, so it lives here
     * rather than being restated in each one.
     */
    public static function narrowed(string $value, int $narrow, int $broad): int
    {
        return $value === '' ? $broad : $narrow;
    }

    private function __construct()
    {
    }
}
