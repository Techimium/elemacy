<?php

namespace Elemacy\Core\Rendering;

defined('ABSPATH') || exit;

use Elemacy\Core\Integrations\Elementor\AtomicElementsAdapter;
use Elemacy\Core\Integrations\Elementor\StylesRendererAdapter;

/**
 * The single place that touches Elementor's atomic-widgets CSS internals
 * (via AtomicElementsAdapter/StylesRendererAdapter — design.md D9) on behalf
 * of anything that needs a post's atomic CSS as a plain string.
 *
 * Elementor's own atomic CSS pipeline (Atomic_Styles_Manager) only generates
 * CSS for post IDs pushed into it via the `elementor/post/render` action, and
 * reads that list exactly once per request from a hook tied to
 * wp_enqueue_scripts — enqueue_styles(), the method that actually renders and
 * writes the CSS, is private and reachable only from that one hook. Once
 * wp_enqueue_scripts has fired (always true by the time a Loop widget's
 * render() executes mid-body), nothing can trigger it for a new post ID that
 * request. This class bypasses that pipeline entirely by calling the same
 * public collection-and-render primitives it uses internally, so it works
 * from anywhere, at any time, including mid-body and inside an AJAX response.
 */
class AtomicStylesRenderer
{
    protected AtomicElementsAdapter $elements;
    protected StylesRendererAdapter $styles_renderer;

    public function __construct(?AtomicElementsAdapter $elements = null, ?StylesRendererAdapter $styles_renderer = null)
    {
        $this->elements = $elements ?? new AtomicElementsAdapter();
        $this->styles_renderer = $styles_renderer ?? new StylesRendererAdapter();
    }

    /**
     * $post_id's atomic CSS for every style that does NOT contain a
     * dynamic-tag-bound value anywhere in it, using Styles_Renderer's own
     * default `.elementor` selector prefix. Mirrors classic Elementor's own
     * base CSS pass, which likewise never bakes in a dynamic-bound value.
     *
     * @param int $post_id
     * @return string
     */
    public function render_base(int $post_id): string
    {
        return $this->render_styles($this->collect_styles($post_id, false), '.elementor');
    }

    /**
     * $post_id's atomic CSS for only the styles that DO contain a
     * dynamic-tag-bound value somewhere in them, scoped under
     * $selector_prefix instead of the default `.elementor` prefix.
     *
     * @param int    $post_id
     * @param string $selector_prefix
     * @return string
     */
    public function render_dynamic(int $post_id, string $selector_prefix): string
    {
        return $this->render_styles($this->collect_styles($post_id, true), $selector_prefix);
    }

    /**
     * Whether any atomic element on $post_id has a style prop bound to a
     * dynamic tag, so callers can skip a render_dynamic() pass when it
     * would produce nothing.
     *
     * @param int $post_id
     * @return bool
     */
    public function has_dynamic_styles(int $post_id): bool
    {
        return !empty($this->collect_styles($post_id, true));
    }

    /**
     * @param array  $styles
     * @param string $selector_prefix
     * @return string
     */
    protected function render_styles(array $styles, string $selector_prefix): string
    {
        return $this->styles_renderer->render($styles, $selector_prefix);
    }

    /**
     * Every atomic element's own `styles` definitions on $post_id whose
     * presence of a dynamic-tag-bound value (anywhere in that element's
     * whole style definition, checked as one unit — never split apart)
     * matches $want_dynamic, merged into one flat array. The same
     * collection Atomic_Widget_Styles builds internally for
     * Atomic_Styles_Manager, reimplemented here so it can be called outside
     * that manager's hook-locked pipeline, with the dynamic/non-dynamic
     * split classic Elementor's own base CSS pass already does natively.
     *
     * @param int  $post_id
     * @param bool $want_dynamic
     * @return array
     */
    protected function collect_styles(int $post_id, bool $want_dynamic): array
    {
        $styles = [];

        foreach ($this->collect_all_element_styles($post_id) as $element_styles) {
            if ($this->contains_dynamic_value($element_styles) !== $want_dynamic) {
                continue;
            }

            $styles = array_merge($styles, $element_styles);
        }

        return $styles;
    }

    /**
     * Every atomic element's own `styles` definition on $post_id, one array
     * per element, kept separate (not yet merged or filtered) so
     * collect_styles() can decide per element whether it belongs in the
     * base or the dynamic pass.
     *
     * @param int $post_id
     * @return array<int, array>
     */
    protected function collect_all_element_styles(int $post_id): array
    {
        return $this->elements->collect_element_styles($post_id);
    }

    /**
     * @param mixed $value
     * @return bool
     */
    protected function contains_dynamic_value($value): bool
    {
        return $this->elements->contains_dynamic_value($value);
    }
}
