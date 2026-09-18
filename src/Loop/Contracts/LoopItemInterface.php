<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

/**
 * One item a loop widget renders its template against, regardless of what kind
 * of data it wraps (a post, a term, a repeater row, an API response object,
 * ...).
 */
interface LoopItemInterface
{
    /**
     * A key unique to this item within one render, safe to use as a CSS
     * class token. Does not need to correspond to a WordPress post — see
     * .
     *
     * @return string
     */
    public function get_identity(): string;

    /**
     * Resolves one field's value for this item. Meaning and available keys
     * are defined by whichever data source produced this item.
     *
     * @param string $key
     * @return mixed
     */
    public function get_field(string $key);

    /**
     * Sets up whatever native context this item type has (e.g. global $post
     * for a WP_Post-backed item), so existing dynamic tags and any third-party
     * widget in the loop item template resolve correctly without knowing this
     * abstraction exists.
     *
     * @return void
     */
    public function enter(): void;

    /**
     * Tears down whatever enter() set up.
     *
     * @return void
     */
    public function exit(): void;
}
