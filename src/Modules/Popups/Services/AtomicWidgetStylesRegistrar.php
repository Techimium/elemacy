<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

/**
 * Registers post IDs with Elementor's atomic widget styles pipeline early
 * enough for their CSS to actually be generated.
 *
 * Elementor's Atomic_Styles_Manager only produces CSS for post IDs it has
 * learned about via the `elementor/post/render` action, and it reads that
 * list exactly once per request — when Frontend::enqueue_styles() fires
 * `after_enqueue_post_styles`, itself hooked on `wp_enqueue_scripts` at
 * priority 20 (Frontend::ENQUEUED_STYLES_PRIORITY). Hooking one priority
 * earlier guarantees these IDs are already known by the time that one-shot
 * read happens, regardless of whether Elementor's own hook or this one runs
 * first at priority 20.
 */
class AtomicWidgetStylesRegistrar
{
    const PRIORITY = 19;

    /**
     * @var callable Returns the post IDs to register for the current request.
     */
    protected $post_ids_provider;

    /**
     * @param callable $post_ids_provider Returns an array of post IDs to register.
     */
    public function __construct(callable $post_ids_provider)
    {
        $this->post_ids_provider = $post_ids_provider;
    }

    /**
     * Wires the early wp_enqueue_scripts hook.
     *
     * @return void
     */
    public function register_hooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'register_atomic_styles'], self::PRIORITY);
    }

    /**
     * Fires elementor/post/render for each provided post ID.
     *
     * @return void
     */
    public function register_atomic_styles(): void
    {
        foreach (($this->post_ids_provider)() as $post_id) {
            do_action('elementor/post/render', (int) $post_id);
        }
    }
}
