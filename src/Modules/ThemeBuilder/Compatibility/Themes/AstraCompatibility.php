<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Compatibility\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

class AstraCompatibility implements ThemeCompatibilityInterface
{
    public function register(ThemeBuilderManager $manager): void
    {
        if ($manager->get_header_id()) {
            add_action('template_redirect', function () {
                remove_action('astra_header', 'astra_header_markup');

                if (class_exists('Astra_Builder_Helper') && !empty(\Astra_Builder_Helper::$is_header_footer_builder_active)) {
                    if (class_exists('Astra_Builder_Header') && method_exists('Astra_Builder_Header', 'get_instance')) {
                        remove_action('astra_header', [\Astra_Builder_Header::get_instance(), 'prepare_header_builder_markup']);
                    }
                }
            }, 10);

            add_action('astra_header', [$manager, 'render_header'], 10);
        }

        if ($manager->get_footer_id()) {
            add_action('template_redirect', function () {
                remove_action('astra_footer', 'astra_footer_markup');

                if (class_exists('Astra_Builder_Helper') && !empty(\Astra_Builder_Helper::$is_header_footer_builder_active)) {
                    if (class_exists('Astra_Builder_Footer') && method_exists('Astra_Builder_Footer', 'get_instance')) {
                        remove_action('astra_footer', [\Astra_Builder_Footer::get_instance(), 'footer_markup']);
                    }
                }
            }, 10);

            add_action('astra_footer', [$manager, 'render_footer'], 10);
        }
    }
}

