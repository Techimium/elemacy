<?php

namespace Elemacy\Integrations\Acf;

defined('ABSPATH') || exit;

use Throwable;

/**
 * The only place that moves ACF's own row cursor (`have_rows()`/`get_sub_field()`).
 *
 * Its enter() puts the cursor on one row at any depth by replaying its path on a
 * cleared loop stack. Each level is opened by field key with one `have_rows()`
 * call, so ACF builds its own loop record. The cursor then checks that it
 * descended into exactly that field and that the row exists, and sets the row
 * index directly. Nothing walks earlier rows, and nothing depends on whatever
 * loop was active before: that stack is restored exactly by exit().
 *
 * isolate() hides the ACF stack while a non-ACF item (a post, a term, a user)
 * is entered, so its own field reads never match an enclosing row's sub-field.
 *
 * One instance can be entered again while already entered (an ancestor read);
 * every enter()/isolate() is undone by its own exit().
 */
final class AcfCursor
{
    /** @var array<int, array<int, array<string, mixed>>> One snapshot per active enter()/isolate(). */
    private array $snapshots = [];

    /**
     * @param int|string                           $root The post ID or options-page ID the top-level field is stored on.
     * @param array<int, array{0: string, 1: int}> $path Field key and zero-based row index per level, outermost first.
     * @return bool False when the path no longer matches the stored rows; the cursor is then unchanged.
     */
    public function enter($root, array $path): bool
    {
        $loop = self::loop();

        if (!$loop || [] === $path || !function_exists('have_rows') || !function_exists('acf_get_loop') || !function_exists('acf_update_loop')) {
            return false;
        }

        $snapshot = $loop->loops;
        $loop->loops = [];

        try {
            foreach (array_values($path) as $level => [$key, $index]) {
                $has_rows = 0 === $level ? have_rows($key, $root) : have_rows($key);
                $active = acf_get_loop('active');

                if (
                    !$has_rows
                    || count($loop->loops) !== $level + 1
                    || !is_array($active)
                    || ($active['field']['key'] ?? null) !== $key
                    || !isset($active['value'][$index])
                ) {
                    $loop->loops = $snapshot;

                    return false;
                }

                acf_update_loop('active', 'i', $index);
            }
        } catch (Throwable $error) {
            $loop->loops = $snapshot;

            return false;
        }

        $this->snapshots[] = $snapshot;

        return true;
    }

    /** Hides every active ACF loop until exit(). */
    public function isolate(): void
    {
        $loop = self::loop();
        $this->snapshots[] = $loop ? $loop->loops : [];

        if ($loop) {
            $loop->loops = [];
        }
    }

    /** Restores the loop stack exactly as the matching enter()/isolate() found it. */
    public function exit(): void
    {
        if ([] === $this->snapshots) {
            return;
        }

        self::restore(array_pop($this->snapshots));
    }

    /** @return array<int, array<string, mixed>> */
    public static function snapshot(): array
    {
        $loop = self::loop();

        return $loop ? $loop->loops : [];
    }

    /** @param array<int, array<string, mixed>> $snapshot */
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
        if (!function_exists('acf')) {
            return null;
        }

        $acf = acf();

        return is_object($acf) && isset($acf->loop) && is_object($acf->loop) ? $acf->loop : null;
    }
}
