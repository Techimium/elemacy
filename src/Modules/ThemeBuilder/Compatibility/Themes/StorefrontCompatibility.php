<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Compatibility\HookedThemeCompatibility;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

/**
 * Storefront prints its header on `storefront_header` and its footer on
 * `storefront_footer`; the template renders just outside each.
 */
class StorefrontCompatibility extends HookedThemeCompatibility
{
    /** Storefront's chrome sits below this; its handheld footer bar (priority 999) is a separate mobile control and is kept. */
    private const CHROME_MAX_PRIORITY = 199;

    protected function chrome_locations(): array
    {
        return [
            'header' => [
                'hook'     => 'storefront_before_header',
                'priority' => 500,
            ],
            'footer' => [
                'hook'     => 'storefront_after_footer',
                'priority' => 500,
            ],
        ];
    }

    protected function chrome_hooks(): array
    {
        return [
            'header' => ['storefront_header' => self::CHROME_MAX_PRIORITY],
            'footer' => ['storefront_footer' => self::CHROME_MAX_PRIORITY],
        ];
    }

    public function register(ThemeBuilderManager $manager): void
    {
        parent::register($manager);

        // The theme's chrome callbacks are gone, but header.php and footer.php
        // still print an empty <header>/<footer> with the theme's padding and
        // background. This hides that empty shell, not any chrome content.
        $shells = [
            'header' => '.site-header',
            'footer' => '.site-footer',
        ];

        foreach ($shells as $side => $selector) {
            if ($manager->replaces_native_chrome($side)) {
                add_action('wp_enqueue_scripts', static function () use ($selector): void {
                    wp_add_inline_style('elementor-frontend', $selector . '{display:none;}');
                }, 50);
            }
        }
    }
}
