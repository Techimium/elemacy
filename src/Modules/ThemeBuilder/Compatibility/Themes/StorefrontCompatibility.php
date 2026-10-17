<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Compatibility\ThemeHookCleaner;
use Elemacy\Modules\ThemeBuilder\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

class StorefrontCompatibility implements ThemeCompatibilityInterface
{
    /** Storefront's chrome sits below this; its handheld footer bar (priority 999) is a separate mobile control and is kept. */
    private const CHROME_MAX_PRIORITY = 199;

    public function register(ThemeBuilderManager $manager): void
    {
        if ($manager->replaces_native_chrome('header')) {
            add_action('template_redirect', function () {
                ThemeHookCleaner::remove_theme_callbacks('storefront_header', self::CHROME_MAX_PRIORITY);
            }, 10);

            add_action('storefront_before_header', [$manager, 'render_header'], 500);

            // The theme's chrome callbacks are gone, but header.php still prints an
            // empty <header> with the theme's padding and background. This hides
            // that empty shell, not any chrome content.
            add_action('wp_enqueue_scripts', function () {
                wp_add_inline_style('elementor-frontend', '.site-header{display:none;}');
            }, 50);
        }

        if ($manager->replaces_native_chrome('footer')) {
            add_action('template_redirect', function () {
                ThemeHookCleaner::remove_theme_callbacks('storefront_footer', self::CHROME_MAX_PRIORITY);
            }, 10);

            add_action('storefront_after_footer', [$manager, 'render_footer'], 500);

            // As for the header: this hides the empty <footer> shell left behind.
            add_action('wp_enqueue_scripts', function () {
                wp_add_inline_style('elementor-frontend', '.site-footer{display:none;}');
            }, 50);
        }
    }
}
