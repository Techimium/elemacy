<?php

namespace Elemacy\Elementor\Compatibility;

defined('ABSPATH') || exit;

/**
 * The single owner of "does the installed Elementor expose this internal
 * class" for every optional/internal Elementor dependency the rendering
 * pipeline touches. See docs/ARCHITECTURE.md § The Elementor boundary.
 */
class Capabilities
{
    /**
     * Whether Elementor is loaded at all. Every other capability below is
     * meaningless without this one; callers still check it independently
     * since a fast-harness stub can omit Elementor's classes selectively.
     */
    public function plugin_loaded(): bool
    {
        return class_exists('\Elementor\Plugin');
    }

    /**
     * Elementor's classic per-post CSS file API
     * (Core\Files\CSS\Post) — ClassicCssRenderer::render_base().
     */
    public function classic_css_files(): bool
    {
        return class_exists('\Elementor\Core\Files\CSS\Post');
    }

    /**
     * Elementor's classic dynamic-CSS generator (Core\DynamicTags\Dynamic_CSS)
     * — ClassicCssRenderer::render_dynamic() via LoopDynamicCss.
     */
    public function dynamic_css(): bool
    {
        return class_exists('\Elementor\Core\DynamicTags\Dynamic_CSS');
    }

    /**
     * Elementor's Dynamic Tags manager, specifically for its
     * DYNAMIC_SETTING_KEY constant — ClassicCssRenderer::has_dynamic_settings().
     */
    public function dynamic_tags_manager(): bool
    {
        return class_exists('\Elementor\Core\DynamicTags\Manager');
    }

    /**
     * Elementor's Atomic Widgets element-traversal utility
     * (Modules\AtomicWidgets\Utils\Utils) — AtomicStylesRenderer's own
     * collection pass, and (until removes the dependency)
     * ClassicCssRenderer::has_dynamic_settings()'s borrowed traversal.
     */
    public function atomic_widgets(): bool
    {
        return class_exists('\Elementor\Modules\AtomicWidgets\Utils\Utils');
    }

    /**
     * Elementor's Global Classes element-identity helper
     * (Modules\GlobalClasses\Utils\Atomic_Elements_Utils) —
     * AtomicStylesRenderer::collect_all_element_styles().
     */
    public function atomic_global_classes(): bool
    {
        return class_exists('\Elementor\Modules\GlobalClasses\Utils\Atomic_Elements_Utils');
    }

    /**
     * Elementor's Atomic styles-to-CSS renderer
     * (Modules\AtomicWidgets\Styles\Styles_Renderer) —
     * AtomicStylesRenderer::render_styles().
     */
    public function atomic_styles_renderer(): bool
    {
        return class_exists('\Elementor\Modules\AtomicWidgets\Styles\Styles_Renderer');
    }

    /**
     * Elementor's Atomic dynamic-prop detector
     * (Modules\AtomicWidgets\DynamicTags\Dynamic_Prop_Type) —
     * AtomicStylesRenderer::contains_dynamic_value().
     */
    public function atomic_dynamic_prop_type(): bool
    {
        return class_exists('\Elementor\Modules\AtomicWidgets\DynamicTags\Dynamic_Prop_Type');
    }
}
