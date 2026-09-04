<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

class KadenceCompatibility implements ThemeCompatibilityInterface
{
    public function register(ThemeBuilderManager $manager): void
    {
        if ($manager->get_header_id()) {
            add_action('template_redirect', function () {
                remove_action('kadence_header', 'Kadence\header_markup');
                remove_action('kadence_top_header', 'Kadence\top_header');
                remove_action('kadence_main_header', 'Kadence\main_header');
                remove_action('kadence_bottom_header', 'Kadence\bottom_header');

                remove_action('kadence_mobile_header', 'Kadence\mobile_header');
                remove_action('kadence_mobile_top_header', 'Kadence\mobile_top_header');
                remove_action('kadence_mobile_main_header', 'Kadence\mobile_main_header');
                remove_action('kadence_mobile_bottom_header', 'Kadence\mobile_bottom_header');
            }, 10);

            add_action('kadence_header', [$manager, 'render_header'], 10);
        }

        if ($manager->get_footer_id()) {
            add_action('template_redirect', function () {
                remove_action('kadence_footer', 'Kadence\footer_markup');
                remove_action('kadence_top_footer', 'Kadence\top_footer');
                remove_action('kadence_middle_footer', 'Kadence\middle_footer');
                remove_action('kadence_bottom_footer', 'Kadence\bottom_footer');
            }, 10);

            add_action('kadence_footer', [$manager, 'render_footer'], 10);
        }
    }
}

