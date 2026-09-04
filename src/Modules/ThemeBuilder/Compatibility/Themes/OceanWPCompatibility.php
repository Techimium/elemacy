<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

class OceanWPCompatibility implements ThemeCompatibilityInterface
{
    public function register(ThemeBuilderManager $manager): void
    {
        if ($manager->get_header_id()) {
            add_action('template_redirect', function () {
                remove_action('ocean_top_bar', 'oceanwp_top_bar_template');
                remove_action('ocean_header', 'oceanwp_header_template');
                remove_action('ocean_page_header', 'oceanwp_page_header_template');
            }, 10);

            add_action('ocean_header', [$manager, 'render_header'], 10);
        }

        if ($manager->get_footer_id()) {
            add_action('template_redirect', function () {
                remove_action('ocean_footer', 'oceanwp_footer_template');
            }, 10);

            add_action('ocean_footer', [$manager, 'render_footer'], 10);
        }
    }
}

