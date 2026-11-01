<?php

namespace Elemacy\Integrations\Acf\Contracts;

defined('ABSPATH') || exit;

/**
 * Puts ACF's own row cursor (`have_rows()`/`get_sub_field()`) on one repeater
 * row at any depth, and puts it back. Resolve a new one per row item; every
 * successful enter() is undone by its own exit().
 *
 * @since 1.0.0
 * @api
 */
interface AcfRowCursorInterface
{
    /**
     * @param int|string                           $root The post ID or options-page ID the top-level field is stored on.
     * @param array<int, array{0: string, 1: int}> $path Field key and zero-based row index per level, outermost first.
     * @return bool False when the path no longer matches the stored rows; the cursor is then unchanged.
     */
    public function enter($root, array $path): bool;

    /** Restores the loop stack exactly as the matching enter() found it. */
    public function exit(): void;
}
