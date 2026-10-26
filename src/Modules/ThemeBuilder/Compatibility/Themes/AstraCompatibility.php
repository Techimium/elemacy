<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Compatibility\HookedThemeCompatibility;
/**
 * Astra prints its header and footer, classic or from its header/footer
 * builder, from callbacks on `astra_header` and `astra_footer`.
 */
class AstraCompatibility extends HookedThemeCompatibility
{
    protected function chrome_locations(): array
    {
        return [
            'header' => ['hook' => 'astra_header'],
            'footer' => ['hook' => 'astra_footer'],
        ];
    }

    protected function chrome_hooks(): array
    {
        return [
            'header' => ['astra_header' => PHP_INT_MAX],
            'footer' => ['astra_footer' => PHP_INT_MAX],
        ];
    }
}
