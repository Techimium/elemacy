<?php

namespace Elemacy\Modules\DynamicTags\Services;

defined('ABSPATH') || exit;

use Elemacy\Modules\Widgets\LoopItems\TermLoopItem;
use Elemacy\Modules\Widgets\Services\LoopContext;
use WP_Term;

/**
 * Resolves the "current term" a taxonomy dynamic tag should read: whichever
 * term a Taxonomy Terms-sourced loop item is currently rendering, falling
 * back to the term the current page's own query is for (a normal taxonomy
 * archive page) when there is no active loop item. Every other dynamic tag
 * in this codebase gets this "works inside a loop and works standalone"
 * duality for free from an ambient WordPress global (global $post); terms
 * have no such global, which is why TermLoopItem::enter()/exit() are no-ops
 * and this resolver exists instead (see loop-data-source-taxonomy design.md
 * D3).
 */
class Taxonomy
{
    public static function get_current_term(): ?WP_Term
    {
        $item = LoopContext::current();

        if ($item instanceof TermLoopItem) {
            return $item->get_term();
        }

        $queried = get_queried_object();

        return $queried instanceof WP_Term ? $queried : null;
    }
}
