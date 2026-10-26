<?php

namespace Elemacy\Modules\Widgets\Widgets\NavMenu\Controls;

defined('ABSPATH') || exit;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;
use Elemacy\Modules\Widgets\Widgets\NavMenu;

/** Style > Main Menu: the desktop menu's items, pointers and dividers. */
final class MainMenuStyleSection
{
    private const ITEM = '{{WRAPPER}} .elemacy-nav-menu__main .elemacy-nav-menu__item';

    /** An item in a hover-like state: hovered, focused, current page, or parent of an open submenu. */
    private const ITEM_HIGHLIGHTED = '{{WRAPPER}} .elemacy-nav-menu__main .elemacy-nav-menu__item:hover,
        {{WRAPPER}} .elemacy-nav-menu__main .elemacy-nav-menu__item.elemacy-nav-menu__link--active,
        {{WRAPPER}} .elemacy-nav-menu__main .elemacy-nav-menu__item.elemacy-nav-menu__link--open,
        {{WRAPPER}} .elemacy-nav-menu__main .elemacy-nav-menu__item:focus';

    public static function register(NavMenu $widget): void
    {
        $widget->start_controls_section('section_style_main-menu', [
            'label'     => esc_html__('Main Menu', 'elemacy'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['layout!' => NavMenu::LAYOUT_DROPDOWN],
        ]);

        $widget->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'menu_typography',
            'global'   => ['default' => Global_Typography::TYPOGRAPHY_PRIMARY],
            'selector' => '{{WRAPPER}} .elemacy-nav-menu__list .elemacy-nav-menu__item',
        ]);

        self::register_state_tabs($widget);
        self::register_divider($widget);

        // Elementor needs a control between the divider group and the pointer
        // width to resolve their overlapping conditions.
        $widget->add_control('hr', [
            'type' => Controls_Manager::DIVIDER,
        ]);

        self::register_spacing($widget);

        $widget->end_controls_section();
    }

    private static function register_state_tabs(NavMenu $widget): void
    {
        $widget->start_controls_tabs('tabs_menu_item_style');

        $widget->start_controls_tab('tab_menu_item_normal', [
            'label' => esc_html__('Normal', 'elemacy'),
        ]);

        $widget->add_control('color_menu_item', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'global'    => ['default' => Global_Colors::COLOR_TEXT],
            'default'   => '',
            'selectors' => [
                self::ITEM => 'color: {{VALUE}}; fill: {{VALUE}};',
            ],
        ]);

        $widget->end_controls_tab();

        $widget->start_controls_tab('tab_menu_item_hover', [
            'label' => esc_html__('Hover', 'elemacy'),
        ]);

        $widget->add_control('color_menu_item_hover', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'global'    => ['default' => Global_Colors::COLOR_ACCENT],
            'selectors' => [
                self::ITEM_HIGHLIGHTED => 'color: {{VALUE}}; fill: {{VALUE}};',
            ],
            'condition' => ['pointer!' => 'background'],
        ]);

        $widget->add_control('color_menu_item_hover_pointer_bg', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#fff',
            'selectors' => [
                self::ITEM_HIGHLIGHTED => 'color: {{VALUE}}',
            ],
            'condition' => ['pointer' => 'background'],
        ]);

        $widget->add_control('pointer_color_menu_item_hover', [
            'label'     => esc_html__('Pointer Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'global'    => ['default' => Global_Colors::COLOR_ACCENT],
            'default'   => '',
            'selectors' => self::pointer_color_selectors(''),
            'condition' => ['pointer!' => ['none', 'text']],
        ]);

        $widget->end_controls_tab();

        $widget->start_controls_tab('tab_menu_item_active', [
            'label' => esc_html__('Active', 'elemacy'),
        ]);

        $widget->add_control('color_menu_item_active', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '',
            'selectors' => [
                self::ITEM . '.elemacy-nav-menu__link--active' => 'color: {{VALUE}}',
            ],
        ]);

        $widget->add_control('pointer_color_menu_item_active', [
            'label'     => esc_html__('Pointer Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '',
            'selectors' => self::pointer_color_selectors('.elemacy-nav-menu__link--active'),
            'condition' => ['pointer!' => ['none', 'text']],
        ]);

        $widget->end_controls_tab();

        $widget->end_controls_tabs();
    }

    /** Line and background pointers are painted with background-color, the framed pointer with its border. */
    private static function pointer_color_selectors(string $state): array
    {
        $item = '.elemacy-nav-menu__item' . $state;

        return [
            "{{WRAPPER}} .elemacy-nav-menu__main:not(.elemacy-nav-menu__main--pointer-framed) $item:before,
            {{WRAPPER}} .elemacy-nav-menu__main:not(.elemacy-nav-menu__main--pointer-framed) $item:after" => 'background-color: {{VALUE}}',
            "{{WRAPPER}} .elemacy-nav-menu__main--pointer-framed $item:before,
            {{WRAPPER}} .elemacy-nav-menu__main--pointer-framed $item:after" => 'border-color: {{VALUE}}',
        ];
    }

    private static function register_divider(NavMenu $widget): void
    {
        $widget->add_control('nav_menu_divider', [
            'label'     => esc_html__('Divider', 'elemacy'),
            'type'      => Controls_Manager::SWITCHER,
            'label_off' => esc_html__('Off', 'elemacy'),
            'label_on'  => esc_html__('On', 'elemacy'),
            'condition' => ['layout' => NavMenu::LAYOUT_HORIZONTAL],
            'selectors' => [
                '{{WRAPPER}}' => '--elemacy-nav-menu-divider-content: "";',
            ],
            'separator' => 'before',
        ]);

        $condition = [
            'nav_menu_divider' => 'yes',
            'layout'           => NavMenu::LAYOUT_HORIZONTAL,
        ];

        $widget->add_control('nav_menu_divider_style', [
            'label'     => esc_html__('Style', 'elemacy'),
            'type'      => Controls_Manager::SELECT,
            'options'   => [
                'solid'  => esc_html__('Solid', 'elemacy'),
                'double' => esc_html__('Double', 'elemacy'),
                'dotted' => esc_html__('Dotted', 'elemacy'),
                'dashed' => esc_html__('Dashed', 'elemacy'),
            ],
            'default'   => 'solid',
            'condition' => $condition,
            'selectors' => [
                '{{WRAPPER}}' => '--elemacy-nav-menu-divider-style: {{VALUE}}',
            ],
        ]);

        $widget->add_control('nav_menu_divider_weight', [
            'label'      => esc_html__('Width', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%', 'em', 'rem', 'vw', 'custom'],
            'range'      => [
                'px'  => [
                    'min' => 1,
                    'max' => 20,
                ],
                'em'  => ['max' => 2],
                'rem' => ['max' => 2],
            ],
            'condition'  => $condition,
            'selectors'  => [
                '{{WRAPPER}}' => '--elemacy-nav-menu-divider-width: {{SIZE}}{{UNIT}}',
            ],
        ]);

        $widget->add_control('nav_menu_divider_height', [
            'label'      => esc_html__('Height', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%', 'em', 'rem', 'vh', 'custom'],
            'range'      => [
                'px'  => [
                    'min' => 1,
                    'max' => 100,
                ],
                'em'  => ['max' => 10],
                'rem' => ['max' => 10],
                '%'   => [
                    'min' => 1,
                    'max' => 100,
                ],
            ],
            'condition'  => $condition,
            'selectors'  => [
                '{{WRAPPER}}' => '--elemacy-nav-menu-divider-height: {{SIZE}}{{UNIT}}',
            ],
        ]);

        $widget->add_control('nav_menu_divider_color', [
            'label'     => esc_html__('Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'global'    => ['default' => Global_Colors::COLOR_TEXT],
            'condition' => $condition,
            'selectors' => [
                '{{WRAPPER}}' => '--elemacy-nav-menu-divider-color: {{VALUE}}',
            ],
        ]);
    }

    private static function register_spacing(NavMenu $widget): void
    {
        $widget->add_responsive_control('pointer_width', [
            'label'      => esc_html__('Pointer Width', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'range'      => [
                'px'  => ['max' => 30],
                'em'  => ['max' => 3],
                'rem' => ['max' => 3],
            ],
            'selectors'  => [
                '{{WRAPPER}} .elemacy-nav-menu__main--pointer-framed .elemacy-nav-menu__item:before' => 'border-width: {{SIZE}}{{UNIT}}',
                '{{WRAPPER}} .elemacy-nav-menu__main--pointer-framed.elemacy-nav-menu__main--animation-draw .elemacy-nav-menu__item:before' => 'border-width: 0 0 {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}}',
                '{{WRAPPER}} .elemacy-nav-menu__main--pointer-framed.elemacy-nav-menu__main--animation-draw .elemacy-nav-menu__item:after' => 'border-width: {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}} 0 0',
                '{{WRAPPER}} .elemacy-nav-menu__main--pointer-framed.elemacy-nav-menu__main--animation-corners .elemacy-nav-menu__item:before' => 'border-width: {{SIZE}}{{UNIT}} 0 0 {{SIZE}}{{UNIT}}',
                '{{WRAPPER}} .elemacy-nav-menu__main--pointer-framed.elemacy-nav-menu__main--animation-corners .elemacy-nav-menu__item:after' => 'border-width: 0 {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}} 0',
                '{{WRAPPER}} .elemacy-nav-menu__main--pointer-underline .elemacy-nav-menu__item:after,
                 {{WRAPPER}} .elemacy-nav-menu__main--pointer-overline .elemacy-nav-menu__item:before,
                 {{WRAPPER}} .elemacy-nav-menu__main--pointer-double-line .elemacy-nav-menu__item:before,
                 {{WRAPPER}} .elemacy-nav-menu__main--pointer-double-line .elemacy-nav-menu__item:after' => 'height: {{SIZE}}{{UNIT}}',
            ],
            'condition'  => ['pointer' => ['underline', 'overline', 'double-line', 'framed']],
        ]);

        $widget->add_responsive_control('padding_horizontal_menu_item', [
            'label'      => esc_html__('Horizontal Padding', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'range'      => [
                'px'  => ['max' => 50],
                'em'  => ['max' => 5],
                'rem' => ['max' => 5],
            ],
            'selectors'  => [
                self::ITEM => 'padding-left: {{SIZE}}{{UNIT}}; padding-right: {{SIZE}}{{UNIT}}',
            ],
        ]);

        $widget->add_responsive_control('padding_vertical_menu_item', [
            'label'      => esc_html__('Vertical Padding', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'range'      => [
                'px'  => ['max' => 50],
                'em'  => ['max' => 5],
                'rem' => ['max' => 5],
            ],
            'selectors'  => [
                self::ITEM => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}}',
            ],
        ]);

        $widget->add_responsive_control('menu_space_between', [
            'label'      => esc_html__('Space Between', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'range'      => [
                'px'  => ['max' => 100],
                'em'  => ['max' => 10],
                'rem' => ['max' => 10],
            ],
            'selectors'  => [
                '{{WRAPPER}}' => '--elemacy-nav-menu-item-margin: calc( {{SIZE}}{{UNIT}} / 2 );',
                '{{WRAPPER}} .elemacy-nav-menu__main:not(.elemacy-nav-menu__main--horizontal) .elemacy-nav-menu__list > li:not(:last-child)' => 'margin-bottom: {{SIZE}}{{UNIT}}',
            ],
        ]);

        $widget->add_responsive_control('border_radius_menu_item', [
            'label'      => esc_html__('Border Radius', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%', 'em', 'rem', 'custom'],
            'selectors'  => [
                '{{WRAPPER}} .elemacy-nav-menu__item:before' => 'border-radius: {{SIZE}}{{UNIT}}',
                '{{WRAPPER}} .elemacy-nav-menu__main--animation-shutter-in-horizontal .elemacy-nav-menu__item:before' => 'border-radius: {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}} 0 0',
                '{{WRAPPER}} .elemacy-nav-menu__main--animation-shutter-in-horizontal .elemacy-nav-menu__item:after' => 'border-radius: 0 0 {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}}',
                '{{WRAPPER}} .elemacy-nav-menu__main--animation-shutter-in-vertical .elemacy-nav-menu__item:before' => 'border-radius: 0 {{SIZE}}{{UNIT}} {{SIZE}}{{UNIT}} 0',
                '{{WRAPPER}} .elemacy-nav-menu__main--animation-shutter-in-vertical .elemacy-nav-menu__item:after' => 'border-radius: {{SIZE}}{{UNIT}} 0 0 {{SIZE}}{{UNIT}}',
            ],
            'condition'  => ['pointer' => 'background'],
        ]);
    }
}
