<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Compatibility\HookedThemeCompatibility;
/**
 * Neve prints its header rows on `neve_do_header` (and its top bar on
 * `neve_do_top_bar`) inside the `<header>` element of its header.php, which
 * stays. Its footer, `<footer>` element included, prints from
 * `neve_do_footer`.
 */
class NeveCompatibility extends HookedThemeCompatibility
{
    protected function chrome_locations(): array
    {
        return [
            'header' => [
                'hook'     => 'neve_do_header',
                'priority' => 0,
            ],
            'footer' => [
                'hook'     => 'neve_do_footer',
                'priority' => 0,
            ],
        ];
    }

    protected function chrome_hooks(): array
    {
        return [
            'header' => [
                'neve_do_header'  => PHP_INT_MAX,
                'neve_do_top_bar' => PHP_INT_MAX,
            ],
            'footer' => ['neve_do_footer' => PHP_INT_MAX],
        ];
    }

    protected function theme_landmark_sides(): array
    {
        return ['header'];
    }
}
