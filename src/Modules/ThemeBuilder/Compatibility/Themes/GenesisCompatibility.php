<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Compatibility\ThemeHookCleaner;
use Elemacy\Modules\ThemeBuilder\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

class GenesisCompatibility implements ThemeCompatibilityInterface
{
    /** Genesis registers its header and footer markup up to priority 15; this adapter's own wrapper opens at 16. */
    private const CHROME_MAX_PRIORITY = 15;

    public function register(ThemeBuilderManager $manager): void
    {
        if ($manager->replaces_native_chrome('header')) {
            add_action('template_redirect', function () {
                ThemeHookCleaner::remove_theme_callbacks('genesis_header', self::CHROME_MAX_PRIORITY);
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

        if ($manager->replaces_native_chrome('footer')) {
            add_action('template_redirect', function () {
                ThemeHookCleaner::remove_theme_callbacks('genesis_footer', self::CHROME_MAX_PRIORITY);
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
