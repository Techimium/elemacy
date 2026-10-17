<?php

namespace Elemacy\Integrations\Acf;

defined('ABSPATH') || exit;

/**
 * Reversibly advances ACF's own native row cursor (`have_rows()`/
 * `the_row()`/`get_sub_field()`) to one target row, then restores exactly what was
 * active before — including an enclosing row iteration's own position — rather
 * than leaving the shared cursor wherever this acquisition last left it.
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
     * @param string     $field
     * @param int|string $post_id
     * @param int        $row_index
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
