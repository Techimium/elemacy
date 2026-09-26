<?php

namespace Elemacy\Elementor\Rendering;

defined('ABSPATH') || exit;

use Elemacy\Elementor\Compatibility\AtomicElementsAdapter;
use Elemacy\Elementor\Compatibility\StylesRendererAdapter;

/**
 * The single place that touches Elementor's atomic-widgets CSS internals (via
 * AtomicElementsAdapter/StylesRendererAdapter) on behalf of anything that
 * needs a post's atomic CSS as a plain string.
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
     * $post_id's atomic CSS for every style that does NOT contain a dynamic-
     * tag-bound value anywhere in it, using Styles_Renderer's own default
     * `.elementor` selector prefix.
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
     * presence of a dynamic-tag-bound value (anywhere in that element's whole
     * style definition, checked as one unit — never split apart) matches
     * $want_dynamic, merged into one flat array.
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
