<?php

namespace Elemacy\Modules\Widgets\LoopItems;

defined('ABSPATH') || exit;

use Elemacy\Modules\DynamicTags\Services\Acf;
use Elemacy\Modules\Widgets\Contracts\LoopItemInterface;
use Elemacy\Modules\Widgets\Support\LoopItemIdentity;
use WP_Post;

/**
 * A loop item backed by a real WP_Post — the reference implementation of
 * LoopItemInterface. enter()/exit() drive the same setup_postdata()/
 * wp_reset_postdata() pair WP_Query::the_post() already does, so every
 * existing dynamic tag and any third-party widget in the loop item template
 * keeps resolving correctly with no changes to any of them (design.md D2).
 */
class PostLoopItem implements LoopItemInterface
{
    protected WP_Post $post;

    public function __construct(WP_Post $post)
    {
        $this->post = $post;
    }

    public function get_identity(): string
    {
        return LoopItemIdentity::sanitize((string) $this->post->ID);
    }

    public function enter(): void
    {
        global $post;

        $post = $this->post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- mirrors WP_Query::the_post(), which every loop item template already relies on.
        setup_postdata($this->post);
    }

    public function exit(): void
    {
        wp_reset_postdata();
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get_field(string $key)
    {
        switch ($key) {
            case 'ID':
                return $this->post->ID;
            case 'title':
                return get_the_title($this->post);
            case 'content':
                return apply_filters('the_content', $this->post->post_content);
            case 'excerpt':
                return get_the_excerpt($this->post);
            case 'date':
                return get_the_date('', $this->post);
            case 'url':
                return get_permalink($this->post);
        }

        // Acf::get_field_value() already falls back to get_post_meta() when
        // ACF isn't active or the key isn't an ACF field, so this covers
        // both ACF fields and plain post meta in one call.
        return Acf::get_field_value($key, $this->post->ID);
    }
}
