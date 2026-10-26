<?php

namespace Elemacy\Modules\Widgets\Widgets\NavMenu\Controls;

defined('ABSPATH') || exit;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elemacy\Modules\Widgets\Widgets\NavMenu;

/** Style > Dropdown: desktop submenus and, below the breakpoint, the whole mobile dropdown. */
final class DropdownStyleSection
{
    private const DROPDOWN = '{{WRAPPER}} .elemacy-nav-menu__dropdown';
    private const LINK = '{{WRAPPER}} .elemacy-nav-menu__dropdown a';
    private const LINK_ACTIVE = '{{WRAPPER}} .elemacy-nav-menu__dropdown a.elemacy-nav-menu__link--active';

    /** A link in a hover-like state: hovered, focused, current page, or parent of an open submenu. */
    private const LINK_HIGHLIGHTED = '{{WRAPPER}} .elemacy-nav-menu__dropdown a:hover,
        {{WRAPPER}} .elemacy-nav-menu__dropdown a:focus,
        {{WRAPPER}} .elemacy-nav-menu__dropdown a.elemacy-nav-menu__link--active,
        {{WRAPPER}} .elemacy-nav-menu__dropdown a.elemacy-nav-menu__link--open';

    public static function register(NavMenu $widget): void
    {
        $widget->start_controls_section('section_style_dropdown', [
            'label' => esc_html__('Dropdown', 'elemacy'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $widget->add_control('dropdown_description', [
            'raw'             => esc_html__('On desktop, this will affect the submenu. On mobile, this will affect the entire menu.', 'elemacy'),
            'type'            => Controls_Manager::RAW_HTML,
            'content_classes' => 'elementor-descriptor',
        ]);

        self::register_state_tabs($widget);
        self::register_box($widget);
        self::register_items($widget);

        $widget->end_controls_section();
    }

    private static function register_state_tabs(NavMenu $widget): void
    {
        $widget->start_controls_tabs('tabs_dropdown_item_style');

        $widget->start_controls_tab('tab_dropdown_item_normal', [
            'label' => esc_html__('Normal', 'elemacy'),
        ]);

        $widget->add_control('color_dropdown_item', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '',
            'selectors' => [
                self::LINK . ', {{WRAPPER}} .elemacy-nav-menu__toggle' => 'color: {{VALUE}}; fill: {{VALUE}};',
            ],
        ]);

        $widget->add_control('background_color_dropdown_item', [
            'label'     => esc_html__('Background Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '',
            'selectors' => [
                self::DROPDOWN => 'background-color: {{VALUE}}',
            ],
        ]);

        $widget->end_controls_tab();

        $widget->start_controls_tab('tab_dropdown_item_hover', [
            'label' => esc_html__('Hover', 'elemacy'),
        ]);

        $widget->add_control('color_dropdown_item_hover', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '',
            'selectors' => [
                self::LINK_HIGHLIGHTED . ',
                {{WRAPPER}} .elemacy-nav-menu__toggle:hover,
                {{WRAPPER}} .elemacy-nav-menu__toggle:focus' => 'color: {{VALUE}}',
            ],
        ]);

        $widget->add_control('background_color_dropdown_item_hover', [
            'label'     => esc_html__('Background Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '',
            'selectors' => [
                self::LINK_HIGHLIGHTED => 'background-color: {{VALUE}}',
            ],
        ]);

        $widget->end_controls_tab();

        $widget->start_controls_tab('tab_dropdown_item_active', [
            'label' => esc_html__('Active', 'elemacy'),
        ]);

        $widget->add_control('color_dropdown_item_active', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '',
            'selectors' => [
                self::LINK_ACTIVE => 'color: {{VALUE}}',
            ],
        ]);

        $widget->add_control('background_color_dropdown_item_active', [
            'label'     => esc_html__('Background Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '',
            'selectors' => [
                self::LINK_ACTIVE => 'background-color: {{VALUE}}',
            ],
        ]);

        $widget->end_controls_tab();

        $widget->end_controls_tabs();
    }

    private static function register_box(NavMenu $widget): void
    {
        $widget->add_group_control(Group_Control_Typography::get_type(), [
            'name'      => 'dropdown_typography',
            'global'    => ['default' => Global_Typography::TYPOGRAPHY_ACCENT],
            'exclude'   => ['line_height'],
            'selector'  => self::DROPDOWN . ' .elemacy-nav-menu__item, ' . self::DROPDOWN . ' .elemacy-nav-menu__sub-item',
            'separator' => 'before',
        ]);

        $widget->add_group_control(Group_Control_Border::get_type(), [
            'name'      => 'dropdown_border',
            'selector'  => self::DROPDOWN,
            'separator' => 'before',
        ]);

        $widget->add_responsive_control('dropdown_border_radius', [
            'label'      => esc_html__('Border Radius', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em', 'rem', 'custom'],
            'selectors'  => [
                self::DROPDOWN => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                self::DROPDOWN . ' li:first-child a' => 'border-top-left-radius: {{TOP}}{{UNIT}}; border-top-right-radius: {{RIGHT}}{{UNIT}};',
                self::DROPDOWN . ' li:last-child a' => 'border-bottom-right-radius: {{BOTTOM}}{{UNIT}}; border-bottom-left-radius: {{LEFT}}{{UNIT}};',
            ],
        ]);

        $widget->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'dropdown_box_shadow',
            'exclude'  => ['box_shadow_position'],
            'selector' => '{{WRAPPER}} .elemacy-nav-menu__main .elemacy-nav-menu__dropdown, {{WRAPPER}} .elemacy-nav-menu__container.elemacy-nav-menu__dropdown',
        ]);
    }

    private static function register_items(NavMenu $widget): void
    {
        $widget->add_responsive_control('padding_horizontal_dropdown_item', [
            'label'      => esc_html__('Horizontal Padding', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'vw', 'custom'],
            'range'      => [
                'vw' => [
                    'min' => 0,
                    'max' => 10,
                ],
            ],
            'selectors'  => [
                self::LINK => 'padding-left: {{SIZE}}{{UNIT}}; padding-right: {{SIZE}}{{UNIT}}',
            ],
            'separator'  => 'before',
        ]);

        $widget->add_responsive_control('padding_vertical_dropdown_item', [
            'label'      => esc_html__('Vertical Padding', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'vh', 'custom'],
            'range'      => [
                'px'  => ['max' => 50],
                'em'  => ['max' => 5],
                'rem' => ['max' => 5],
                'vh'  => [
                    'min' => 0,
                    'max' => 10,
                ],
            ],
            'selectors'  => [
                self::LINK => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}}',
            ],
        ]);

        $widget->add_control('heading_dropdown_divider', [
            'label'     => esc_html__('Divider', 'elemacy'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $widget->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'dropdown_divider',
            'selector' => self::DROPDOWN . ' li:not(:last-child)',
            'exclude'  => ['width'],
        ]);

        $widget->add_control('dropdown_divider_width', [
            'label'      => esc_html__('Border Width', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%', 'em', 'rem', 'vw', 'custom'],
            'range'      => [
                'px'  => ['max' => 50],
                'em'  => ['max' => 5],
                'rem' => ['max' => 5],
            ],
            'selectors'  => [
                self::DROPDOWN . ' li:not(:last-child)' => 'border-bottom-width: {{SIZE}}{{UNIT}}',
            ],
            'condition'  => ['dropdown_divider_border!' => ''],
        ]);

        $widget->add_responsive_control('dropdown_top_distance', [
            'label'      => esc_html__('Distance', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'range'      => [
                'px'  => [
                    'min' => -100,
                    'max' => 100,
                ],
                'em'  => [
                    'min' => -10,
                    'max' => 10,
                ],
                'rem' => [
                    'min' => -10,
                    'max' => 10,
                ],
            ],
            'selectors'  => [
                '{{WRAPPER}} .elemacy-nav-menu__main > .elemacy-nav-menu__list > li > .elemacy-nav-menu__dropdown, {{WRAPPER}} .elemacy-nav-menu__container.elemacy-nav-menu__dropdown' => 'margin-top: {{SIZE}}{{UNIT}} !important',
            ],
            'separator'  => 'before',
        ]);
    }
}
