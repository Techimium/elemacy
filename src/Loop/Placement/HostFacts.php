<?php

namespace Elemacy\Loop\Placement;

defined('ABSPATH') || exit;

use Elemacy\Loop\Address\ItemAddressResolver;
use Elemacy\Loop\Contracts\NativePostItemInterface;
use Elemacy\Loop\LoopContext;
use Elemacy\Loop\Rendering\LoopTemplateRenderStack;
use WP_Post;
use WP_Term;

/**
 * The "current post" and "current term" a public loop sees, decided by the
 * server for this request the same way the editor decides them, so a source's
 * public policy never has to read globals or trust request data. Only publicly
 * viewable objects are ever supplied.
 *
 * Inside a loop item it also supplies the enclosing item's address, a
 * fingerprint of that item chain's content and the item's render key, so a
 * later page can rebuild exactly that chain and refuse it once it changed.
 */
final class HostFacts
{
    public const POST_ID = 'post_id';
    public const TERM_ID = 'term_id';
    public const PARENT = 'parent';
    public const PARENT_FP = 'parent_fp';
    public const PARENT_RENDER = 'parent_render';

    /** Every fact name this class owns; a placement resolver never supplies one. */
    public const NAMES = [self::POST_ID, self::TERM_ID, self::PARENT, self::PARENT_FP, self::PARENT_RENDER];

    /**
     * The verified current post of the later page being rendered, if any.
     *
     * @var int|null
     */
    private static ?int $assumed_post_id = null;

    /**
     * Makes $post_id the current post for the rest of a later-page render,
     * which has no viewed singular post of its own. Pass the returned value
     * back to restore.
     *
     * @param int|null $post_id A post the signed context proved, and that was checked again as public.
     * @return int|null The previously assumed post.
     */
    public static function assume_post(?int $post_id): ?int
    {
        $previous = self::$assumed_post_id;
        self::$assumed_post_id = $post_id;

        return $previous;
    }

    /** @return array{post_id?: int, term_id?: int, parent?: string, parent_fp?: string, parent_render?: string} */
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

        return $facts + self::parent_facts();
    }

    /**
     * The enclosing loop item's chain, while a real loop render (one template
     * per frame) is under way and every item in it can be rebuilt.
     *
     * @return array{parent?: string, parent_fp?: string, parent_render?: string}
     */
    private static function parent_facts(): array
    {
        $frame = LoopContext::current_frame();
        $address = $frame ? $frame->address() : null;

        if (null === $address || !$address->is_rebuildable() || count(LoopContext::frames()) !== count(LoopTemplateRenderStack::stack())) {
            return [];
        }

        // The token is readable by the visitor, so a chain with any item that
        // could not be rebuilt for them (a draft or private post, a removed
        // row) is never signed, as a non-public post_id never is.
        $resolver = new ItemAddressResolver();

        if (null === $resolver->resolve($address)) {
            return [];
        }

        return [
            self::PARENT => $address->canonical(),
            self::PARENT_FP => $resolver->fingerprint($address, LoopContext::stack()),
            self::PARENT_RENDER => $frame->render_key(),
        ];
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
     * inside a post item still belongs to that post), else the post a later
     * page's signed context proved, else the viewed singular object. On an
     * archive outside a post item there is none.
     */
    private static function current_post(): ?WP_Post
    {
        foreach (array_reverse(LoopContext::stack()) as $item) {
            if ($item instanceof NativePostItemInterface) {
                return $item->get_post();
            }
        }

        if (null !== self::$assumed_post_id) {
            $post = get_post(self::$assumed_post_id);

            return $post instanceof WP_Post ? $post : null;
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
