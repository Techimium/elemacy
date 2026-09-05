<?php

namespace Elemacy\Core\Rendering;

defined('ABSPATH') || exit;

use Elemacy\Core\Rendering\Support\LoopDynamicCss;

/**
 * The single place that touches Elementor's classic CSS pipeline
 * (Core\Files\CSS\Post, Core\DynamicTags\Dynamic_CSS) on behalf of anything
 * that needs a post's classic CSS rather than an enqueued file — currently
 * Loop widgets, which render post IDs that aren't the current page and can't
 * rely on Elementor's own <head> enqueue timing.
 */
class ClassicCssRenderer
{
    /**
     * render_base() outcome: Elementor already wrote this post's CSS to a
     * cached, cache-busted file on disk — the caller should reference it by
     * URL instead of inlining its content.
     */
    public const DELIVERY_LINK = 'link';

    /**
     * render_base() outcome: this post's CSS must be printed as literal CSS
     * text (no external file exists for it, or none should be trusted).
     */
    public const DELIVERY_INLINE = 'inline';

    /**
     * render_base() outcome: there is no CSS to deliver for this post.
     */
    public const DELIVERY_NONE = 'none';

    /**
     * @var array<int, true> Post IDs Elementor's own automatic dynamic-CSS
     *                        auto-print is suppressed for, this request.
     */
    protected static array $suppressed_dynamic_auto_print = [];

    /**
     * @var bool Whether the should_enqueue filter has been hooked yet.
     */
    protected static bool $suppression_hooked = false;

    /**
     * Disables Elementor's own automatic "print this document's dynamic CSS
     * on enqueue" behavior for $post_id (see elementor/css-file/post/enqueue
     * -> DynamicTags\Manager::after_enqueue_post_css()). That mechanism
     * assumes a document only ever renders as its own page; called once per
     * loop item (as get_builder_content_for_display() does internally, via
     * Post_CSS::enqueue()), it prints exactly once — for whichever item
     * happens to trigger it first — scoped to the template's shared
     * selector, and every other item silently inherits that one frozen
     * value. render_dynamic() is this module's own, correctly-scoped
     * replacement, so Elementor's version must never run for a loop
     * template. Idempotent and safe to call once per template per request.
     *
     * @param int $post_id
     * @return void
     */
    public function suppress_automatic_dynamic_css(int $post_id): void
    {
        self::$suppressed_dynamic_auto_print[$post_id] = true;

        if (self::$suppression_hooked) {
            return;
        }

        self::$suppression_hooked = true;

        add_filter('elementor/css-file/dynamic/should_enqueue', static function ($should_enqueue, $filtered_post_id) {
            if (isset(self::$suppressed_dynamic_auto_print[$filtered_post_id])) {
                return false;
            }

            return $should_enqueue;
        }, 10, 2);
    }

    /**
     * A post's own classic base CSS delivery. Also the point where Elementor
     * learns which of the post's elements use a dynamic tag (persisted to
     * postmeta as a side effect of Post_CSS::update()) — render_dynamic()
     * depends on this having run at least once.
     *
     * Elementor already writes this content to a cached, cache-busted file
     * on disk as a side effect of update() (design.md D1) — reusing it via
     * DELIVERY_LINK avoids re-walking the post's whole element/control tree
     * on every call, which get_content() would otherwise always do. Callers
     * must not treat DELIVERY_LINK's content as CSS text.
     *
     * @param int $post_id
     * @return array{type: string, content: string} `type` is one of the
     *              DELIVERY_* constants; `content` is a URL for
     *              DELIVERY_LINK, CSS text for DELIVERY_INLINE, or '' for
     *              DELIVERY_NONE.
     */
    public function render_base(int $post_id): array
    {
        if (!class_exists('\Elementor\Core\Files\CSS\Post')) {
            return [
                'type' => self::DELIVERY_NONE,
                'content' => '',
            ];
        }

        $css_file = \Elementor\Core\Files\CSS\Post::create($post_id);
        $meta = $css_file->get_meta();

        // Base::enqueue() also ORs in is_update_required() here, but that
        // method is protected and Post (unlike Post_Local_Cache) never
        // overrides it — it's hardcoded false on Base, so it's both
        // inaccessible from here and would contribute nothing for this
        // class. An empty status is the only staleness signal available
        // (and needed) for a plain Post_CSS file.
        if ('' === $meta['status']) {
            $css_file->update();
            $meta = $css_file->get_meta();
        }

        // Elementor's own cache says an external file exists — but never
        // trust that blindly (design.md D2): a missing file here (e.g. an
        // uploads/ directory that didn't survive a migration) must fall
        // back to inline, never a broken <link>.
        if (\Elementor\Core\Files\CSS\Post::CSS_STATUS_FILE === $meta['status']
            && file_exists($css_file->get_path())) {
            return [
                'type' => self::DELIVERY_LINK,
                'content' => $css_file->get_url(),
            ];
        }

        if (\Elementor\Core\Files\CSS\Post::CSS_STATUS_EMPTY === $meta['status']) {
            return [
                'type' => self::DELIVERY_NONE,
                'content' => '',
            ];
        }

        return [
            'type' => self::DELIVERY_INLINE,
            'content' => $css_file->get_content(),
        ];
    }

    /**
     * $template_id's CSS as it should resolve for one specific loop item,
     * with the selector rewritten from the template's own `.elementor-{id}`
     * to $selector_prefix so it only applies to that one rendered item.
     *
     * $item_identity is a LoopItemInterface::get_identity() value — a
     * string unique to one item within this render, not necessarily a
     * WordPress post ID. LoopDynamicCss never uses it to look up a post
     * (see design.md D4); it only needs to match the token this method
     * rewrites out of the generated CSS below.
     *
     * @param int    $template_id
     * @param string $item_identity
     * @param string $selector_prefix
     * @return string
     */
    public function render_dynamic(int $template_id, string $item_identity, string $selector_prefix): string
    {
        if (!class_exists('\Elementor\Core\DynamicTags\Dynamic_CSS')) {
            return '';
        }

        $css = (new LoopDynamicCss($item_identity, $template_id))->get_content();

        if ($css === '') {
            return '';
        }

        return str_replace('.elementor-' . $item_identity, $selector_prefix, $css);
    }

    /**
     * Whether $post_id has any control value bound to a dynamic tag, so
     * callers can skip render_dynamic() entirely when it would be a no-op.
     *
     * @param int $post_id
     * @return bool
     */
    public function has_dynamic_settings(int $post_id): bool
    {
        if (!class_exists('\Elementor\Modules\AtomicWidgets\Utils\Utils')
            || !class_exists('\Elementor\Core\DynamicTags\Manager')) {
            return false;
        }

        $found = false;

        \Elementor\Modules\AtomicWidgets\Utils\Utils::traverse_post_elements(
            (string) $post_id,
            function (array $element_data) use (&$found) {
                if ($found) {
                    return;
                }

                if (!empty($element_data['settings'][\Elementor\Core\DynamicTags\Manager::DYNAMIC_SETTING_KEY])) {
                    $found = true;
                }
            }
        );

        return $found;
    }
}
