<?php

namespace Elemacy\Core\Integrations\Elementor;

defined('ABSPATH') || exit;

/**
 * The one place AtomicStylesRenderer reaches into Elementor's Atomic
 * styles-to-CSS renderer (Modules\AtomicWidgets\Styles\Styles_Renderer) and
 * its breakpoints configuration — design.md D9's "small adapters for
 * version-specific calls".
 */
class StylesRendererAdapter
{
    protected Capabilities $capabilities;

    public function __construct(?Capabilities $capabilities = null)
    {
        $this->capabilities = $capabilities ?? new Capabilities();
    }

    /**
     * Whether the Atomic styles-renderer capability this adapter wraps is
     * actually present on the installed Elementor.
     */
    public function available(): bool
    {
        return $this->capabilities->atomic_styles_renderer() && $this->capabilities->plugin_loaded();
    }

    /**
     * Renders $styles (Atomic style definitions) as CSS text scoped under
     * $selector_prefix. Empty (never a fatal) when $styles is empty or the
     * Atomic styles-renderer capability is absent.
     *
     * @param array  $styles
     * @param string $selector_prefix
     * @return string
     */
    public function render(array $styles, string $selector_prefix): string
    {
        if (empty($styles) || !$this->available()) {
            return '';
        }

        $breakpoints = \Elementor\Plugin::$instance->breakpoints->get_breakpoints_config();

        return \Elementor\Modules\AtomicWidgets\Styles\Styles_Renderer::make($breakpoints, $selector_prefix)
            ->render($styles);
    }
}
