<?php

namespace Elemacy\Modules\DynamicTags\Services;

defined('ABSPATH') || exit;

use Elemacy\Modules\Widgets\LoopItems\UserLoopItem;
use Elemacy\Modules\Widgets\Services\LoopContext;
use WP_User;

/**
 * Resolves the "current user" a User dynamic tag should read: whichever
 * user a Users-sourced loop item is currently rendering, falling back to
 * the user the current page's own query is for (an author archive page)
 * when there is no active loop item. Mirrors Taxonomy::get_current_term() —
 * users have no "current user in a content loop" WordPress global the way
 * posts do (global $post), which is why UserLoopItem::enter()/exit() are
 * no-ops and this resolver exists instead (design.md D4).
 *
 * Deliberately not reused by the existing Author/* tags: those stay scoped
 * to the current post's author (get_post()->post_author), per the
 * documented boundary in proposal.md/spec.md — an Author tag inside a
 * Users-sourced loop item template is not rewired to this resolver.
 */
class User
{
    public static function get_current_user(): ?WP_User
    {
        $item = LoopContext::current();

        if ($item instanceof UserLoopItem) {
            return $item->get_user();
        }

        $queried = get_queried_object();

        return $queried instanceof WP_User ? $queried : null;
    }
}
