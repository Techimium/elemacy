<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Compatibility\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

class StorefrontCompatibility implements ThemeCompatibilityInterface
{
    public function register(ThemeBuilderManager $manager): void
    {
        if ($manager->get_header_id()) {
            add_action('template_redirect', function () {
                for ($priority = 0; $priority < 200; $priority++) {
                    remove_all_actions('storefront_header', $priority);
                }
            }, 10);

            add_action('storefront_before_header', [$manager, 'render_header'], 500);

            add_action('wp_enqueue_scripts', function () {
                wp_add_inline_style('elementor-frontend', '.site-header{display:none;}');
            }, 50);
        }

        if ($manager->get_footer_id()) {
            add_action('template_redirect', function () {
                for ($priority = 0; $priority < 200; $priority++) {
                    remove_all_actions('storefront_footer', $priority);
                }
            }, 10);

            add_action('storefront_after_footer', [$manager, 'render_footer'], 500);

            add_action('wp_enqueue_scripts', function () {
                wp_add_inline_style('elementor-frontend', '.site-footer{display:none;}');
            }, 50);
        }
    }
}

