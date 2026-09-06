<?php

namespace Elemacy\Core\Rendering;

defined('ABSPATH') || exit;

/**
 * The single place that touches Elementor's atomic-widgets CSS internals
 * (Utils::traverse_post_elements, Atomic_Elements_Utils, Styles_Renderer) on
 * behalf of anything that needs a post's atomic CSS as a plain string.
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
        if (empty($styles) || !class_exists('\Elementor\Modules\AtomicWidgets\Styles\Styles_Renderer')
            || !class_exists('\Elementor\Plugin')) {
            return '';
        }

        $breakpoints = \Elementor\Plugin::$instance->breakpoints->get_breakpoints_config();

        return \Elementor\Modules\AtomicWidgets\Styles\Styles_Renderer::make($breakpoints, $selector_prefix)
            ->render($styles);
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
        if (!class_exists('\Elementor\Modules\AtomicWidgets\Utils\Utils')
            || !class_exists('\Elementor\Modules\GlobalClasses\Utils\Atomic_Elements_Utils')) {
            return [];
        }

        $per_element_styles = [];

        \Elementor\Modules\AtomicWidgets\Utils\Utils::traverse_post_elements(
            (string) $post_id,
            function (array $element_data) use (&$per_element_styles) {
                $element_type = \Elementor\Modules\GlobalClasses\Utils\Atomic_Elements_Utils::get_element_type($element_data);
                $element_instance = \Elementor\Modules\GlobalClasses\Utils\Atomic_Elements_Utils::get_element_instance($element_type);

                if (!\Elementor\Modules\AtomicWidgets\Utils\Utils::is_atomic($element_instance)) {
                    return;
                }

                $per_element_styles[] = $element_data['styles'] ?? [];
            }
        );

        return $per_element_styles;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    protected function contains_dynamic_value($value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if (class_exists('\Elementor\Modules\AtomicWidgets\DynamicTags\Dynamic_Prop_Type')
            && \Elementor\Modules\AtomicWidgets\DynamicTags\Dynamic_Prop_Type::is_dynamic_prop_value($value)) {
            return true;
        }

        foreach ($value as $item) {
            if ($this->contains_dynamic_value($item)) {
                return true;
            }
        }

        return false;
    }
}
