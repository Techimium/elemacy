<?php

namespace Elemacy\Modules\Widgets\Widgets\NavMenu;

defined('ABSPATH') || exit;

use Elementor\Plugin;

/**
 * The breakpoints a Nav Menu can collapse at, and the media rules that collapse it.
 *
 * The values come from the site's active Elementor breakpoints, custom values
 * included, so the Breakpoint control's labels and the switch between main menu
 * and toggle always agree. Laptop and Widescreen are not offered: the dropdown is
 * a mobile pattern.
 */
final class BreakpointStyles
{
    /** Eligible breakpoints, in the order the control lists them. */
    private const KEYS = ['mobile', 'mobile_extra', 'tablet', 'tablet_extra'];

    /** @return array<string, array{label: string, value: int}> Active eligible breakpoints by key. */
    public static function breakpoints(): array
    {
        $manager = Plugin::$instance->breakpoints ?? null;

        if (!is_object($manager) || !method_exists($manager, 'get_active_breakpoints')) {
            // Elementor's own defaults.
            return [
                'mobile' => [
                    'label' => __('Mobile Portrait', 'elemacy'),
                    'value' => 767,
                ],
                'tablet' => [
                    'label' => __('Tablet Portrait', 'elemacy'),
                    'value' => 1024,
                ],
            ];
        }

        $active = $manager->get_active_breakpoints();
        $breakpoints = [];

        foreach (self::KEYS as $key) {
            if (isset($active[$key])) {
                $breakpoints[$key] = [
                    'label' => (string) $active[$key]->get_label(),
                    'value' => (int) $active[$key]->get_value(),
                ];
            }
        }

        return $breakpoints;
    }

    /**
     * At or below a widget's breakpoint the main menu hides; above it the toggle
     * and the mobile dropdown hide.
     */
    public static function css(): string
    {
        $css = '';

        foreach (self::breakpoints() as $key => $breakpoint) {
            $scope = '.' . Css::WRAPPER . 'breakpoint-' . $key;

            $css .= sprintf(
                '@media (max-width:%1$dpx){%2$s .%3$s{display:none}}'
                . '@media (min-width:%4$dpx){%2$s .%5$s,%2$s .%6$s.%7$s{display:none}%2$s nav.%7$s.%6$s{overflow-y:hidden}}',
                $breakpoint['value'],
                $scope,
                Css::MAIN,
                $breakpoint['value'] + 1,
                Css::TOGGLE,
                Css::CONTAINER,
                Css::DROPDOWN
            );
        }

        return $css;
    }
}
