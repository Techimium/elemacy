<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Compatibility\ThemeHookCleaner;
use Elemacy\Modules\ThemeBuilder\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

class NeveCompatibility implements ThemeCompatibilityInterface
{
    public function register(ThemeBuilderManager $manager): void
    {
        if ($manager->replaces_native_chrome('header')) {
            add_action('template_redirect', function () use ($manager) {
                ThemeHookCleaner::remove_theme_callbacks('neve_do_header');
                ThemeHookCleaner::remove_theme_callbacks('neve_do_top_bar');
                add_action('neve_do_header', [$manager, 'render_header'], 0);
            }, 10);
        }

        if ($manager->replaces_native_chrome('footer')) {
            add_action('template_redirect', function () use ($manager) {
                ThemeHookCleaner::remove_theme_callbacks('neve_do_footer');
                add_action('neve_do_footer', [$manager, 'render_footer'], 0);
            }, 10);
        }
    }
}
