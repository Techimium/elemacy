<?php

namespace Elemacy\Loop\Context;

defined('ABSPATH') || exit;

use WP_Post;

/**
 * Reversibly acquires the native WordPress "current post" context
 * (design.md D8) — everything `setup_postdata()` changes — and restores
 * exactly the state that was active before enter() ran, not the site's
 * main-query post. `wp_reset_postdata()` alone cannot do this: it always
 * resets to `$wp_query->post`, which is only correct when there is no
 * enclosing loop item of its own to return to.
 *
 * One instance is scoped to one enter()/exit() pair; a fresh instance must
 * be used for each nested acquisition so an inner scope's snapshot never
 * overwrites an outer one still awaiting its own exit().
 */
final class WordPressPostScope
{
    private const UNSET_MARKER = "\0elemacy-unset\0";

    /** @var array<string,mixed>|null */
    private ?array $snapshot = null;

    public function enter(WP_Post $post): void
    {
        $this->snapshot = self::capture();

        $GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- mirrors WP_Query::the_post(), which every loop item template already relies on.
        setup_postdata($post);
    }

    public function exit(): void
    {
        if (null === $this->snapshot) {
            return;
        }

        self::restore($this->snapshot);
        $this->snapshot = null;
    }

    /** @return array<string,mixed> */
    private static function capture(): array
    {
        $snapshot = [];

        foreach (self::keys() as $key) {
            $snapshot[$key] = array_key_exists($key, $GLOBALS) ? $GLOBALS[$key] : self::UNSET_MARKER;
        }

        return $snapshot;
    }

    /** @param array<string,mixed> $snapshot */
    private static function restore(array $snapshot): void
    {
        foreach ($snapshot as $key => $value) {
            if (self::UNSET_MARKER === $value) {
                unset($GLOBALS[$key]);
            } else {
                $GLOBALS[$key] = $value;
            }
        }
    }

    /** @return string[] */
    private static function keys(): array
    {
        return ['post', 'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages'];
    }
}
