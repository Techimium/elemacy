<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Compatibility\HookedThemeCompatibility;
/**
 * Blocksy switches its header and footer builders off through its own
 * filters, and the template renders where they would have started.
 */
class BlocksyCompatibility extends HookedThemeCompatibility
{
    protected function chrome_locations(): array
    {
        return [
            'header' => [
                'hook'     => 'blocksy:header:before',
                'priority' => 5,
            ],
            'footer' => [
                'hook'     => 'blocksy:footer:before',
                'priority' => 5,
            ],
        ];
    }

    protected function chrome_switches(): array
    {
        return [
            'header' => ['blocksy:builder:header:enabled'],
            'footer' => ['blocksy:builder:footer:enabled'],
        ];
    }
}
