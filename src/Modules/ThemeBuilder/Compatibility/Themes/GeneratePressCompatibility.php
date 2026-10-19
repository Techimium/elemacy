<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Compatibility\HookedThemeCompatibility;
/**
 * GeneratePress prints its header on `generate_header` and its footer (with
 * the footer widgets) on `generate_footer`. Its top bar and its navigation,
 * when positioned above or below the header rather than inside it, hang on
 * `generate_before_header`/`generate_after_header`, which also carry page
 * content (the page hero), so those are named one by one.
 */
class GeneratePressCompatibility extends HookedThemeCompatibility
{
    protected function chrome_locations(): array
    {
        return [
            'header' => ['hook' => 'generate_header'],
            'footer' => ['hook' => 'generate_footer'],
        ];
    }

    protected function chrome_hooks(): array
    {
        return [
            'header' => ['generate_header' => PHP_INT_MAX],
            'footer' => ['generate_footer' => PHP_INT_MAX],
        ];
    }

    protected function chrome_callbacks(): array
    {
        return [
            'header' => [
                ['generate_before_header', 'generate_top_bar', 5],
                ['generate_before_header', 'generate_add_navigation_before_header', 5],
                ['generate_after_header', 'generate_add_navigation_after_header', 5],
            ],
        ];
    }
}
