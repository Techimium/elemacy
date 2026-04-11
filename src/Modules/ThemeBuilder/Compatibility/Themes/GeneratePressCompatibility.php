<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Compatibility\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

class GeneratePressCompatibility implements ThemeCompatibilityInterface
{
    public function register(ThemeBuilderManager $manager): void
    {
        if ($manager->get_header_id()) {
            add_action('template_redirect', function () {
                remove_action('generate_header', 'generate_construct_header');
            }, 10);

            add_action('generate_header', [$manager, 'render_header'], 10);
        }

        if ($manager->get_footer_id()) {
            add_action('template_redirect', function () {
                remove_action('generate_footer', 'generate_construct_footer_widgets', 5);
                remove_action('generate_footer', 'generate_construct_footer');
            }, 10);

            add_action('generate_footer', [$manager, 'render_footer'], 10);
        }
    }
}

