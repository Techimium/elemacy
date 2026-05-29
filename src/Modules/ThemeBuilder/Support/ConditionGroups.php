<?php

namespace Elemacy\Modules\ThemeBuilder\Support;

defined('ABSPATH') || exit;

/**
 * Localized labels for condition groups (keyed by ConditionInterface::get_type()).
 */
class ConditionGroups
{
    public static function label(string $type): string
    {
        switch ($type) {
            case 'general':
                return __('General', 'elemacy');
            case 'singular':
                return __('Singular', 'elemacy');
            case 'archive':
                return __('Archive', 'elemacy');
            default:
                return ucfirst($type);
        }
    }
}
