<?php

namespace Elemacy\Core\Rendering;

defined('ABSPATH') || exit;

use Elemacy\Core\Rendering\Support\LoopDynamicCss;

/**
 * The single place that touches Elementor's classic CSS pipeline
 * (Core\Files\CSS\Post, Core\DynamicTags\Dynamic_CSS) on behalf of anything
 * that needs a post's classic CSS as a plain string rather than an enqueued
 * file — currently Loop widgets, which render post IDs that aren't the
 * current page and can't rely on Elementor's own <head> enqueue timing.
 */
class ClassicCssRenderer
{
    /**
     * A post's own classic CSS, freshly rendered. Also the point where
     * Elementor learns which of the post's elements use a dynamic tag
     * (persisted to postmeta as a side effect of Post_CSS::update()) —
     * render_dynamic() depends on this having run at least once.
     *
     * @param int $post_id
     * @return string
     */
    public function render_base(int $post_id): string
    {
        if (!class_exists('\Elementor\Core\Files\CSS\Post')) {
            return '';
        }

        $css_file = \Elementor\Core\Files\CSS\Post::create($post_id);

        if (empty(get_post_meta($post_id, \Elementor\Core\Files\CSS\Post::META_KEY, true))) {
            $css_file->update();
        }

        return $css_file->get_content();
    }

    /**
     * $template_id's CSS as it should resolve for one specific loop item,
     * with the selector rewritten from the template's own `.elementor-{id}`
     * to $selector_prefix so it only applies to that one rendered item.
     *
     * @param int    $template_id
     * @param int    $item_post_id
     * @param string $selector_prefix
     * @return string
     */
    public function render_dynamic(int $template_id, int $item_post_id, string $selector_prefix): string
    {
        if (!class_exists('\Elementor\Core\DynamicTags\Dynamic_CSS')) {
            return '';
        }

        $css = (new LoopDynamicCss($item_post_id, $template_id))->get_content();

        if ($css === '') {
            return '';
        }

        return str_replace('.elementor-' . $item_post_id, $selector_prefix, $css);
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
