<?php

namespace Elemacy\Modules\DynamicTags\Services;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\NativeTermItemInterface;
use Elemacy\Loop\LoopContext;
use WP_Term;

/**
 * Resolves the "current term" a taxonomy dynamic tag should read: whichever
 * term a Taxonomy Terms-sourced loop item is currently rendering, falling back
 * to the term the current page's own query is for (a normal taxonomy archive
 * page) when there is no active loop item.
 */
class Taxonomy
{
    public static function get_current_term(): ?WP_Term
    {
        $item = LoopContext::current();

        if ($item instanceof NativeTermItemInterface) {
            return $item->get_term();
        }

        $queried = get_queried_object();

        return $queried instanceof WP_Term ? $queried : null;
    }
}
