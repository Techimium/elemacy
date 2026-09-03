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
     * $post_id's atomic CSS, scoped under $selector_prefix instead of
     * Styles_Renderer's own default `.elementor` prefix.
     *
     * @param int    $post_id
     * @param string $selector_prefix
     * @return string
     */
    public function render(int $post_id, string $selector_prefix = '.elementor'): string
    {
        $styles = $this->collect_styles($post_id);

        if (empty($styles) || !class_exists('\Elementor\Modules\AtomicWidgets\Styles\Styles_Renderer')
            || !class_exists('\Elementor\Plugin')) {
            return '';
        }

        $breakpoints = \Elementor\Plugin::$instance->breakpoints->get_breakpoints_config();

        return \Elementor\Modules\AtomicWidgets\Styles\Styles_Renderer::make($breakpoints, $selector_prefix)
            ->render($styles);
    }

    /**
     * Whether any atomic element on $post_id has a style prop bound to a
     * dynamic tag, so callers can skip a per-item render() pass when it
     * would produce the exact same CSS every time.
     *
     * @param int $post_id
     * @return bool
     */
    public function has_dynamic_styles(int $post_id): bool
    {
        $found = false;

        foreach ($this->collect_styles($post_id) as $style) {
            if ($this->contains_dynamic_value($style)) {
                $found = true;
                break;
            }
        }

        return $found;
    }

    /**
     * Every atomic element's own `styles` definitions on $post_id, merged
     * into one flat array — the same collection Atomic_Widget_Styles builds
     * internally for Atomic_Styles_Manager, reimplemented here so it can be
     * called outside that manager's hook-locked pipeline.
     *
     * @param int $post_id
     * @return array
     */
    protected function collect_styles(int $post_id): array
    {
        if (!class_exists('\Elementor\Modules\AtomicWidgets\Utils\Utils')
            || !class_exists('\Elementor\Modules\GlobalClasses\Utils\Atomic_Elements_Utils')) {
            return [];
        }

        $styles = [];

        \Elementor\Modules\AtomicWidgets\Utils\Utils::traverse_post_elements(
            (string) $post_id,
            function (array $element_data) use (&$styles) {
                $element_type = \Elementor\Modules\GlobalClasses\Utils\Atomic_Elements_Utils::get_element_type($element_data);
                $element_instance = \Elementor\Modules\GlobalClasses\Utils\Atomic_Elements_Utils::get_element_instance($element_type);

                if (!\Elementor\Modules\AtomicWidgets\Utils\Utils::is_atomic($element_instance)) {
                    return;
                }

                $styles = array_merge($styles, $element_data['styles'] ?? []);
            }
        );

        return $styles;
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
