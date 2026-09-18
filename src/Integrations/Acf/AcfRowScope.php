<?php

namespace Elemacy\Integrations\Acf;

defined('ABSPATH') || exit;

/**
 * Reversibly advances ACF's own native row cursor (`have_rows()`/
 * `the_row()`/`get_sub_field()`) to one target row, then restores exactly
 * what was active before — including an enclosing row iteration's own
 * position — rather than leaving the shared cursor wherever this
 * acquisition last left it (design.md D8). An empty `exit()` and
 * forward-only walking are insufficient once a nested or repeated render
 * shares the same field+post_id: see docs/ARCHITECTURE-CONTRACTS.md for the
 * confirmed row-cursor-sharing behavior this restores against.
 *
 * ACF exposes no official save/restore API for arbitrary loop position, so
 * this is the one place that reaches into ACF's own internal loop stack
 * (`acf()->loop->loops`, a public property on ACF core's own global
 * singleton — not a private/protected internal reached via reflection).
 * `snapshot()`/`restore()` are also used by AcfRepeaterQuery's own eager
 * have_rows()/the_row() pre-walk, which is a read-only "how many rows"
 * query and must leave no lasting cursor side effect of its own.
 *
 * One instance is scoped to one enter()/exit() pair; a fresh instance must
 * be used for each nested acquisition so an inner scope's snapshot never
 * overwrites an outer one still awaiting its own exit().
 */
final class AcfRowScope
{
    /** @var array<int,array<string,mixed>>|null */
    private ?array $snapshot = null;

    /**
     * Advances the row cursor for $field/$post_id forward, one
     * have_rows()/the_row() step at a time, until it reaches $row_index —
     * the same walk AcfRepeaterRowLoopItem::enter() always performed, now
     * preceded by a snapshot so exit() can undo it.
     *
     * @param int|string $post_id
     */
    public function enter(string $field, $post_id, int $row_index): void
    {
        $this->snapshot = self::snapshot();

        while (have_rows($field, $post_id)) {
            the_row();

            if (get_row_index() - 1 >= $row_index) {
                return;
            }
        }
    }

    public function exit(): void
    {
        if (null === $this->snapshot) {
            return;
        }

        self::restore($this->snapshot);
        $this->snapshot = null;
    }

    /** @return array<int,array<string,mixed>> */
    public static function snapshot(): array
    {
        return self::loop() ? self::loop()->loops : [];
    }

    /** @param array<int,array<string,mixed>> $snapshot */
    public static function restore(array $snapshot): void
    {
        $loop = self::loop();

        if ($loop) {
            $loop->loops = $snapshot;
        }
    }

    /** @return object|null ACF's own global acf_loop instance, or null when ACF is unavailable. */
    private static function loop()
    {
        if (!function_exists('acf') || !isset(acf()->loop)) {
            return null;
        }

        return acf()->loop;
    }
}
