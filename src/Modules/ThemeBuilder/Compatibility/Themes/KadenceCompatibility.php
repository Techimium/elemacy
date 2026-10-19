<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Compatibility\HookedThemeCompatibility;
/**
 * Kadence prints its whole header (desktop and mobile rows) from
 * `kadence_header` and its whole footer from `kadence_footer`.
 */
class KadenceCompatibility extends HookedThemeCompatibility
{
    protected function chrome_locations(): array
    {
        return [
            'header' => ['hook' => 'kadence_header'],
            'footer' => ['hook' => 'kadence_footer'],
        ];
    }

    protected function chrome_hooks(): array
    {
        return [
            'header' => ['kadence_header' => PHP_INT_MAX],
            'footer' => ['kadence_footer' => PHP_INT_MAX],
        ];
    }
}
