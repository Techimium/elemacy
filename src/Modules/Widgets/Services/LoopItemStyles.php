<?php

namespace Elemacy\Modules\Widgets\Services;

defined('ABSPATH') || exit;

use Elemacy\Core\Rendering\AtomicStylesRenderer;
use Elemacy\Core\Rendering\ClassicCssRenderer;

/**
 * Prints CSS at render position for a loop item template — both its shared
 * base styles and, when it uses a dynamic tag anywhere in a control or
 * style, a per-item pass scoped to that one rendered item.
 *
 * Printed at render position rather than enqueued: a loop template's post ID
 * isn't known until the widget itself renders (mid-body, or inside an AJAX
 * response, neither of which can reach Elementor's <head> enqueue timing),
 * and a per-item dynamic pass has to vary per item regardless. See
 * openspec/changes/fix-loop-widget-css-bridging/design.md for that
 * reasoning, and openspec/changes/cache-loop-base-css/design.md for why the
 * base pass prefers linking to Elementor's own cached CSS file over always
 * inlining freshly-regenerated content.
 *
 * The only two places this reaches into Elementor are ClassicCssRenderer and
 * AtomicStylesRenderer — everything else here is Loop-specific bookkeeping.
 */
class LoopItemStyles
{
    /**
     * @var array<int, true> Template IDs whose base CSS has already been
     *                        printed this request.
     */
    protected static array $printed_base_css = [];

    /**
     * @var array<int, bool> has_dynamic_content() results, keyed by
     *                        template ID, cached for the request.
     */
    protected static array $has_dynamic_content = [];

    protected ClassicCssRenderer $classic;

    protected AtomicStylesRenderer $atomic;

    public function __construct(?ClassicCssRenderer $classic = null, ?AtomicStylesRenderer $atomic = null)
    {
        $this->classic = $classic ?? new ClassicCssRenderer();
        $this->atomic = $atomic ?? new AtomicStylesRenderer();
    }

    /**
     * The template's shared, non-dynamic CSS — printed once per template per
     * request, regardless of how many loop widgets or items reference it.
     *
     * @param int $template_id
     * @return void
     */
    public function print_base_css(int $template_id): void
    {
        $this->classic->suppress_automatic_dynamic_css($template_id);

        if (isset(self::$printed_base_css[$template_id])) {
            return;
        }

        self::$printed_base_css[$template_id] = true;

        $classic = $this->classic->render_base($template_id);
        $atomic_css = $this->atomic->render_base($template_id);

        // Atomic base CSS has no cached file to link to (design.md
        // Non-Goals) — when the classic half links out, it prints
        // separately instead of being merged into the linked file's
        // content. Every other outcome merges exactly as before.
        if (ClassicCssRenderer::DELIVERY_LINK === $classic['type']) {
            $this->echo_link('elemacy-loop-base-' . $template_id, $classic['content']);
            $this->echo_style('elemacy-loop-base-atomic-' . $template_id, $atomic_css);

            return;
        }

        $this->echo_style('elemacy-loop-base-' . $template_id, $classic['content'] . $atomic_css);
    }

    /**
     * One loop item's dynamic style override, scoped to that item alone. A
     * no-op when the template has no dynamic-tag-bound style anywhere, so a
     * non-dynamic template costs nothing beyond print_base_css().
     *
     * $item_identity comes from the rendering LoopItemInterface's own
     * get_identity() — a string unique to that item within this render, not
     * necessarily a WordPress post ID (see design.md D4).
     *
     * @param int    $template_id
     * @param string $item_identity
     * @return void
     */
    public function print_item_css(int $template_id, string $item_identity): void
    {
        if (!$this->has_dynamic_content($template_id)) {
            return;
        }

        $selector = '.elemacy-loop-item-' . $item_identity;

        $css = $this->classic->render_dynamic($template_id, $item_identity, $selector)
            . $this->atomic->render_dynamic($template_id, $selector);

        $this->echo_style('elemacy-loop-item-' . $item_identity, $css);
    }

    /**
     * @param int $template_id
     * @return bool
     */
    protected function has_dynamic_content(int $template_id): bool
    {
        if (!isset(self::$has_dynamic_content[$template_id])) {
            self::$has_dynamic_content[$template_id] =
                $this->classic->has_dynamic_settings($template_id)
                || $this->atomic->has_dynamic_styles($template_id);
        }

        return self::$has_dynamic_content[$template_id];
    }

    /**
     * @param string $id_attr
     * @param string $css
     * @return void
     */
    protected function echo_style(string $id_attr, string $css): void
    {
        if ($css === '') {
            return;
        }

        echo '<style id="' . esc_attr($id_attr) . '">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS generated by Elementor's own renderers, not user input; matches Elementor core's own convention of not escaping generated CSS content.
    }

    /**
     * @param string $id_attr
     * @param string $url
     * @return void
     */
    protected function echo_link(string $id_attr, string $url): void
    {
        if ($url === '') {
            return;
        }

        echo '<link rel="stylesheet" id="' . esc_attr($id_attr) . '" href="' . esc_url($url) . '">'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- wp_enqueue_style() cannot be used here: this prints mid-body (or inside an AJAX response), after wp_head()/wp_print_styles() already fired, so an enqueued handle would never reach the page (see design.md D3 in openspec/changes/cache-loop-base-css).
    }
}
