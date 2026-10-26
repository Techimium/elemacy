<?php

namespace Elemacy\Modules\Widgets\Widgets\NavMenu\Controls;

defined('ABSPATH') || exit;

use Elementor\Controls_Manager;
use Elemacy\Modules\Widgets\Widgets\NavMenu;

/** Style > Toggle Button: the hamburger that opens the mobile dropdown. */
final class ToggleStyleSection
{
    private const TOGGLE = '{{WRAPPER}} .elemacy-nav-menu__toggle';

    public static function register(NavMenu $widget): void
    {
        $widget->start_controls_section('style_toggle', [
            'label'     => esc_html__('Toggle Button', 'elemacy'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => [
                'toggle!'   => '',
                'dropdown!' => NavMenu::BREAKPOINT_NONE,
            ],
        ]);

        $widget->start_controls_tabs('tabs_toggle_style');

        $widget->start_controls_tab('tab_toggle_style_normal', [
            'label' => esc_html__('Normal', 'elemacy'),
        ]);

        // `div.` outranks the Dropdown text colour, which also colours the toggle.
        $widget->add_control('toggle_color', [
            'label'     => esc_html__('Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} div.elemacy-nav-menu__toggle' => 'color: {{VALUE}}',
                '{{WRAPPER}} div.elemacy-nav-menu__toggle svg' => 'fill: {{VALUE}}',
            ],
        ]);

        $widget->add_control('toggle_background_color', [
            'label'     => esc_html__('Background Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                self::TOGGLE => 'background-color: {{VALUE}}',
            ],
        ]);

        $widget->end_controls_tab();

        $widget->start_controls_tab('tab_toggle_style_hover', [
            'label' => esc_html__('Hover', 'elemacy'),
        ]);

        $widget->add_control('toggle_color_hover', [
            'label'     => esc_html__('Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} div.elemacy-nav-menu__toggle:hover, {{WRAPPER}} div.elemacy-nav-menu__toggle:focus' => 'color: {{VALUE}}',
                '{{WRAPPER}} div.elemacy-nav-menu__toggle:hover svg, {{WRAPPER}} div.elemacy-nav-menu__toggle:focus svg' => 'fill: {{VALUE}}',
            ],
        ]);

        $widget->add_control('toggle_background_color_hover', [
            'label'     => esc_html__('Background Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                self::TOGGLE . ':hover, ' . self::TOGGLE . ':focus' => 'background-color: {{VALUE}}',
            ],
        ]);

        $widget->end_controls_tab();

        $widget->end_controls_tabs();

        $widget->add_responsive_control('toggle_size', [
            'label'      => esc_html__('Size', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'range'      => [
                'px'  => ['min' => 15],
                'em'  => ['max' => 1.5],
                'rem' => ['max' => 1.5],
            ],
            'selectors'  => [
                '{{WRAPPER}}' => '--elemacy-nav-menu-toggle-size: {{SIZE}}{{UNIT}}',
            ],
            'separator'  => 'before',
        ]);

        $widget->add_responsive_control('toggle_border_width', [
            'label'      => esc_html__('Border Width', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%', 'em', 'rem', 'vw', 'custom'],
            'range'      => [
                'px'  => ['max' => 20],
                'em'  => ['max' => 2],
                'rem' => ['max' => 2],
            ],
            'selectors'  => [
                self::TOGGLE => 'border-width: {{SIZE}}{{UNIT}}',
            ],
        ]);

        $widget->add_responsive_control('toggle_border_radius', [
            'label'      => esc_html__('Border Radius', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%', 'em', 'rem', 'custom'],
            'selectors'  => [
                self::TOGGLE => 'border-radius: {{SIZE}}{{UNIT}}',
            ],
        ]);

        $widget->end_controls_section();
    }
}
