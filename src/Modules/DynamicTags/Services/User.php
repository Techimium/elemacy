<?php

namespace Elemacy\Modules\DynamicTags\Services;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\NativeUserItemInterface;
use Elemacy\Loop\LoopContext;
use WP_User;

/**
 * Resolves the "current user" a User dynamic tag should read: whichever user a
 * Users-sourced loop item is currently rendering, falling back to the user the
 * current page's own query is for (an author archive page) when there is no
 * active loop item.
 */
class User
{
    public static function get_current_user(): ?WP_User
    {
        $item = LoopContext::current();

        if ($item instanceof NativeUserItemInterface) {
            return $item->get_user();
        }

        $queried = get_queried_object();

        return $queried instanceof WP_User ? $queried : null;
    }
}
