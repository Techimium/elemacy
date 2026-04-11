<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Compatibility\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

class GenesisCompatibility implements ThemeCompatibilityInterface
{
    public function register(ThemeBuilderManager $manager): void
    {
        if ($manager->get_header_id()) {
            add_action('template_redirect', function () {
                for ($priority = 0; $priority < 16; $priority++) {
                    remove_all_actions('genesis_header', $priority);
                }
            }, 10);

            add_action('genesis_header', function () {
                if (!function_exists('genesis_markup') || !function_exists('genesis_structural_wrap')) {
                    return;
                }

                genesis_markup([
                    'html5' => '<header %s>',
                    'xhtml' => '<div id="header">',
                    'context' => 'site-header',
                ]);
                genesis_structural_wrap('header');
            }, 16);

            add_action('genesis_header', [$manager, 'render_header'], 16);

            add_action('genesis_header', function () {
                if (!function_exists('genesis_markup') || !function_exists('genesis_structural_wrap')) {
                    return;
                }

                genesis_structural_wrap('header', 'close');
                genesis_markup([
                    'html5' => '</header>',
                    'xhtml' => '</div>',
                ]);
            }, 25);
        }

        if ($manager->get_footer_id()) {
            add_action('template_redirect', function () {
                for ($priority = 0; $priority < 16; $priority++) {
                    remove_all_actions('genesis_footer', $priority);
                }
            }, 10);

            add_action('genesis_footer', function () {
                if (!function_exists('genesis_markup') || !function_exists('genesis_structural_wrap')) {
                    return;
                }

                genesis_markup([
                    'html5' => '<footer %s>',
                    'xhtml' => '<div id="footer" class="footer">',
                    'context' => 'site-footer',
                ]);
                genesis_structural_wrap('footer', 'open');
            }, 16);

            add_action('genesis_footer', [$manager, 'render_footer'], 16);

            add_action('genesis_footer', function () {
                if (!function_exists('genesis_markup') || !function_exists('genesis_structural_wrap')) {
                    return;
                }

                genesis_structural_wrap('footer', 'close');
                genesis_markup([
                    'html5' => '</footer>',
                    'xhtml' => '</div>',
                ]);
            }, 25);
        }
    }
}

