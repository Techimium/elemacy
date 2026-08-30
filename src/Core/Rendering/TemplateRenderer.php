<?php

namespace Elemacy\Core\Rendering;

defined('ABSPATH') || exit;

/**
 * Renders an Elementor document's content by post ID, wherever on the page
 * it's needed. Independent of TemplateAssetsRegistrar: rendering never
 * depends on when (or whether) a document's assets were registered.
 */
class TemplateRenderer
{
    /**
     * @param int $id The post ID to render.
     * @return string
     */
    public function render(int $id): string
    {
        if (!class_exists('\Elementor\Plugin')) {
            return '';
        }

        return \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($id);
    }
}
