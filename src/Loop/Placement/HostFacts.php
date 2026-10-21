<?php

namespace Elemacy\Loop\Placement;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\NativePostItemInterface;
use Elemacy\Loop\LoopContext;
use WP_Post;
use WP_Term;

/**
 * The "current post" and "current term" a public loop sees, decided by the
 * server for this request the same way the editor decides them, so a source's
 * public policy never has to read globals or trust request data. Only publicly
 * viewable objects are ever supplied.
 */
final class HostFacts
{
    public const POST_ID = 'post_id';
    public const TERM_ID = 'term_id';

    /** @return array{post_id?: int, term_id?: int} */
    public static function current(): array
    {
        $facts = [];

        $post = self::current_post();
        if ($post && self::is_public_post($post)) {
            $facts[self::POST_ID] = (int) $post->ID;
        }

        $term = self::current_term();
        if ($term && self::is_public_term($term)) {
            $facts[self::TERM_ID] = (int) $term->term_id;
        }

        return $facts;
    }

    /** @param WP_Post|null $post */
    public static function is_public_post($post): bool
    {
        return $post instanceof WP_Post && 'publish' === $post->post_status && !post_password_required($post);
    }

    /** @param WP_Term|null $term */
    public static function is_public_term($term): bool
    {
        return $term instanceof WP_Term && is_taxonomy_viewable($term->taxonomy);
    }

    /**
     * The post of the nearest enclosing loop item that wraps one (an ACF row
     * inside a post item still belongs to that post), else the viewed
     * singular object. On an archive outside a post item there is none.
     */
    private static function current_post(): ?WP_Post
    {
        foreach (array_reverse(LoopContext::stack()) as $item) {
            if ($item instanceof NativePostItemInterface) {
                return $item->get_post();
            }
        }

        if (!is_singular()) {
            return null;
        }

        $queried = get_queried_object();

        return $queried instanceof WP_Post ? $queried : null;
    }

    private static function current_term(): ?WP_Term
    {
        if (!is_category() && !is_tag() && !is_tax()) {
            return null;
        }

        $queried = get_queried_object();

        return $queried instanceof WP_Term ? $queried : null;
    }
}
