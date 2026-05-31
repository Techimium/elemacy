<?php

namespace Elemacy\Modules\Popups\Support;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;

class DisplayDefaults
{
    /**
     * Default DisplaySettings for a given popup type.
     *
     * This is the schema source of truth for the display settings shape.
     *
     * @param string $type
     * @return array
     */
    public static function for_type(string $type): array
    {
        switch ($type) {
            case PopupTypes::TOPBAR:
                $defaults = [
                    'position' => 'top',
                    'overlay' => [
                        'enabled' => false,
                        'color' => '#000000',
                        'opacity' => 0.6,
                    ],
                    'animation' => [
                        'in' => 'slide-down',
                        'out' => 'slide-up',
                    ],
                    'close' => [
                        'button' => true,
                        'auto_close_s' => 0,
                        'on_overlay_click' => false,
                        'on_esc' => false,
                    ],
                    'prevent_body_scroll' => false,
                    'width' => [
                        'value' => 100,
                        'unit' => '%',
                    ],
                    'height' => [
                        'value' => 0,
                        'unit' => 'auto',
                    ],
                    'z_index' => 9990,
                ];
                break;

            case PopupTypes::FLOATING:
                $defaults = [
                    'position' => 'bottom-right',
                    'overlay' => [
                        'enabled' => false,
                        'color' => '#000000',
                        'opacity' => 0.6,
                    ],
                    'animation' => [
                        'in' => 'fade',
                        'out' => 'fade',
                    ],
                    'close' => [
                        'button' => false,
                        'auto_close_s' => 0,
                        'on_overlay_click' => false,
                        'on_esc' => false,
                    ],
                    'prevent_body_scroll' => false,
                    'width' => [
                        'value' => 360,
                        'unit' => 'px',
                    ],
                    'height' => [
                        'value' => 0,
                        'unit' => 'auto',
                    ],
                    'z_index' => 9980,
                ];
                break;

            case PopupTypes::BANNER:
                // A banner floats like the floating element, but reads as a wide
                // promotional bar (bottom-anchored, dismissible) by default.
                $defaults = [
                    'position' => 'bottom',
                    'overlay' => [
                        'enabled' => false,
                        'color' => '#000000',
                        'opacity' => 0.6,
                    ],
                    'animation' => [
                        'in' => 'slide-up',
                        'out' => 'slide-down',
                    ],
                    'close' => [
                        'button' => true,
                        'auto_close_s' => 0,
                        'on_overlay_click' => false,
                        'on_esc' => false,
                    ],
                    'prevent_body_scroll' => false,
                    'width' => [
                        'value' => 720,
                        'unit' => 'px',
                    ],
                    'height' => [
                        'value' => 0,
                        'unit' => 'auto',
                    ],
                    'z_index' => 9970,
                ];
                break;

            case PopupTypes::POPUP:
            default:
                $defaults = [
                    'position' => 'center',
                    'overlay' => [
                        'enabled' => true,
                        'color' => '#000000',
                        'opacity' => 0.6,
                    ],
                    'animation' => [
                        'in' => 'fade',
                        'out' => 'fade',
                    ],
                    'close' => [
                        'button' => true,
                        'auto_close_s' => 0,
                        'on_overlay_click' => true,
                        'on_esc' => true,
                    ],
                    'prevent_body_scroll' => true,
                    'width' => [
                        'value' => 600,
                        'unit' => 'px',
                    ],
                    'height' => [
                        'value' => 0,
                        'unit' => 'auto',
                    ],
                    'z_index' => 9999,
                ];
                break;
        }

        return apply_filters(Hooks::POPUP_DISPLAY_SETTINGS_FILTER, $defaults, $type);
    }
}
