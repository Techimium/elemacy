<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Compatibility\HookedThemeCompatibility;
/**
 * OceanWP prints its top bar on `ocean_top_bar`, its header on
 * `ocean_header` and its footer on `ocean_footer`. Its page title bar
 * (`ocean_page_header`) is page content, not chrome, and is kept.
 */
class OceanWPCompatibility extends HookedThemeCompatibility
{
    protected function chrome_locations(): array
    {
        return [
            'header' => ['hook' => 'ocean_header'],
            'footer' => ['hook' => 'ocean_footer'],
        ];
    }

    protected function chrome_hooks(): array
    {
        return [
            'header' => [
                'ocean_top_bar' => PHP_INT_MAX,
                'ocean_header'  => PHP_INT_MAX,
            ],
            'footer' => ['ocean_footer' => PHP_INT_MAX],
        ];
    }
}
