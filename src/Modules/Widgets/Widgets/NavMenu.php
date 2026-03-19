<?php

namespace Elemacy\Modules\Widgets\Widgets;

use Elemacy\Modules\Widgets\Walkers\NavMenuWalker;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Icons_Manager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class NavMenu extends BaseWidget
{
    public function get_name()
    {
        return 'elemacy_nav_menu';
    }

    public function get_title()
    {
        return esc_html__('Elemacy Nav Menu', 'elemacy');
    }

    public function get_icon()
    {
        // Use a more appropriate icon than the BaseWidget default.
        return 'eicon-nav-menu';
    }

    public function get_keywords()
    {
        return ['nav', 'menu', 'navigation', 'elemacy'];
    }

    /**
     * Optional: allow a dedicated style handle for this widget.
     *
     * @return array
     */
    public function get_style_depends()
    {
        return ['elemacy-nav-menu'];
    }

    /**
     * Script dependencies.
     *
     * @return array
     */
    public function get_script_depends()
    {
        return ['elemacy-nav-menu'];
    }

    /**
     * Get available WordPress menus as slug => name options.
     *
     * @return array
     */
    protected function get_available_menus()
    {
        $menus = wp_get_nav_menus();

        if (empty($menus)) {
            return [];
        }

        $options = [];

        foreach ($menus as $menu) {
            $options[$menu->slug] = $menu->name;
        }

        return $options;
    }

    protected function register_controls()
    {
        $this->register_layout_controls();
        $this->register_menu_style_controls();
        $this->register_submenu_style_controls();
        $this->register_toggle_style_controls();
        $this->register_toggle_close_style_controls();
    }

    /**
     * Layout & content controls.
     */
    protected function register_layout_controls(): void
    {
        $this->start_controls_section(
            'section_layout',
            [
                'label' => esc_html__('Layout', 'elemacy'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $menus = $this->get_available_menus();

        if (!empty($menus)) {
            $this->add_control(
                'menu',
                [
                    'label' => esc_html__('Menu', 'elemacy'),
                    'type' => Controls_Manager::SELECT,
                    'options' => $menus,
                    'default' => array_keys($menus)[0],
                    'save_default' => true,
                    'description' => sprintf(
                        /* translators: 1: Link opening tag, 2: Link closing tag. */
                        esc_html__('Go to the %1$sMenus screen%2$s to manage your menus.', 'elemacy'),
                        sprintf('<a href="%s" target="_blank">', esc_url(admin_url('nav-menus.php'))),
                        '</a>'
                    ),
                ]
            );
        } else {
            $this->add_control(
                'menu',
                [
                    'type' => Controls_Manager::ALERT,
                    'alert_type' => 'info',
                    'heading' => esc_html__('No menus found', 'elemacy'),
                    'content' => sprintf(
                        /* translators: 1: Link opening tag, 2: Link closing tag. */
                        esc_html__('Go to the %1$sMenus screen%2$s to create one.', 'elemacy'),
                        sprintf('<a href="%s" target="_blank">', esc_url(admin_url('nav-menus.php?action=edit&menu=0'))),
                        '</a>'
                    ),
                    'separator' => 'after',
                ]
            );
        }

        $this->add_control(
            'layout',
            [
                'label' => esc_html__('Layout', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => 'horizontal',
                'options' => [
                    'horizontal' => esc_html__('Horizontal', 'elemacy'),
                    'vertical' => esc_html__('Vertical', 'elemacy'),
                    'stacked' => esc_html__('Stacked (Full Width)', 'elemacy'),
                ],
            ]
        );

        $this->add_responsive_control(
            'alignment',
            [
                'label' => esc_html__('Alignment', 'elemacy'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'flex-start' => [
                        'title' => esc_html__('Start', 'elemacy'),
                        'icon' => 'eicon-align-start-h',
                    ],
                    'center' => [
                        'title' => esc_html__('Center', 'elemacy'),
                        'icon' => 'eicon-align-center-h',
                    ],
                    'flex-end' => [
                        'title' => esc_html__('End', 'elemacy'),
                        'icon' => 'eicon-align-end-h',
                    ],
                    'space-between' => [
                        'title' => esc_html__('Justify', 'elemacy'),
                        'icon' => 'eicon-align-stretch-h',
                    ],
                ],
                'default' => 'flex-start',
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__list' => 'justify-content: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'mobile_breakpoint',
            [
                'label' => esc_html__('Mobile Breakpoint', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => 'tablet',
                'options' => [
                    'none' => esc_html__('None (always expanded)', 'elemacy'),
                    'mobile' => esc_html__('Mobile', 'elemacy'),
                    'tablet' => esc_html__('Tablet & below', 'elemacy'),
                ],
            ]
        );

        $this->add_control(
            'show_toggle',
            [
                'label' => esc_html__('Show Toggle on Mobile', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'elemacy'),
                'label_off' => esc_html__('No', 'elemacy'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_toggle_label',
            [
                'label' => esc_html__('Show Toggle Label', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'elemacy'),
                'label_off' => esc_html__('No', 'elemacy'),
                'return_value' => 'yes',
                'default' => 'no',
                'condition' => [
                    'show_toggle' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'toggle_label',
            [
                'label' => esc_html__('Toggle Label', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => 'Menu',
                'condition' => [
                    'show_toggle_label' => 'yes',
                ],
            ]
        );

        $this->add_responsive_control(
            'toggle_alignment',
            [
                'label' => esc_html__('Toggle Alignment', 'elemacy'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'start' => [
                        'title' => esc_html__('Start', 'elemacy'),
                        'icon' => 'eicon-align-start-h',
                    ],
                    'center' => [
                        'title' => esc_html__('Center', 'elemacy'),
                        'icon' => 'eicon-align-center-h',
                    ],
                    'end' => [
                        'title' => esc_html__('End', 'elemacy'),
                        'icon' => 'eicon-align-end-h',
                    ],
                ],
                'default' => 'end',
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__inner' => 'justify-content: {{VALUE}};',
                ],
                'condition' => [
                    'show_toggle' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'toggle_open_icon',
            [
                'label' => esc_html__('Toggle Icon', 'elemacy'),
                'type' => Controls_Manager::ICONS,
                'default' => [
                    'value' => 'eicon-menu-bar',
                    'library' => 'eicon',
                ],
            ]
        );

        $this->add_control(
            'toggle_close_icon',
            [
                'label' => esc_html__('Close Icon', 'elemacy'),
                'type' => Controls_Manager::ICONS,
                'default' => [
                    'value' => 'eicon-close',
                    'library' => 'eicon',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Main menu style controls.
     */
    protected function register_menu_style_controls(): void
    {
        $this->start_controls_section(
            'section_style_menu',
            [
                'label' => esc_html__('Menu', 'elemacy'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'menu_typography',
                'selector' => '{{WRAPPER}} .elemacy-nav__menu a.elemacy-nav__link',
            ]
        );

        $this->start_controls_tabs('tabs_menu_colors');

        $this->start_controls_tab(
            'tab_menu_normal',
            [
                'label' => esc_html__('Normal', 'elemacy'),
            ]
        );

        $this->add_control(
            'menu_color',
            [
                'label' => esc_html__('Text Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__menu a.elemacy-nav__link' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'menu_background',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__menu a.elemacy-nav__link' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_menu_hover',
            [
                'label' => esc_html__('Hover', 'elemacy'),
            ]
        );

        $this->add_control(
            'menu_color_hover',
            [
                'label' => esc_html__('Text Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__menu a.elemacy-nav__link:hover,
                     {{WRAPPER}} .elemacy-nav__menu a.elemacy-nav__link:focus' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'menu_background_hover',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__menu a.elemacy-nav__link:hover,
                     {{WRAPPER}} .elemacy-nav__menu a.elemacy-nav__link:focus' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_menu_active',
            [
                'label' => esc_html__('Active', 'elemacy'),
            ]
        );

        $this->add_control(
            'menu_color_active',
            [
                'label' => esc_html__('Text Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__menu a.elemacy-nav__link.elemacy-is-active' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'menu_background_active',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__menu a.elemacy-nav__link.elemacy-is-active' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control(
            'menu_item_gap',
            [
                'label' => esc_html__('Item Gap', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem', 'custom'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 60,
                    ],
                ],
                'default' => [
                    'size' => 20,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__list' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'menu_item_padding',
            [
                'label' => esc_html__('Item Padding', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem', 'custom'],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__menu a.elemacy-nav__link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'menu_item_border',
                'selector' => '{{WRAPPER}} .elemacy-nav__menu a.elemacy-nav__link',
            ]
        );

        $this->add_responsive_control(
            'menu_item_border_radius',
            [
                'label' => esc_html__('Item Border Radius', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em', 'rem', 'custom'],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__menu a.elemacy-nav__link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Dropdown style controls.
     */
    protected function register_submenu_style_controls(): void
    {
        $sub_selector = '{{WRAPPER}} .elemacy-nav:not(.is-open) .elemacy-nav__list .elemacy-nav__item .elemacy-nav__submenu';
        $sub_links = '{{WRAPPER}} .elemacy-nav:not(.is-open) .elemacy-nav__list .elemacy-nav__item .elemacy-nav__submenu .elemacy-nav__link';
        $sub_links_hover = '{{WRAPPER}} .elemacy-nav:not(.is-open) .elemacy-nav__list .elemacy-nav__item .elemacy-nav__submenu .elemacy-nav__link:hover';
        $sub_links_active = '{{WRAPPER}} .elemacy-nav:not(.is-open) .elemacy-nav__list .elemacy-nav__item .elemacy-nav__submenu .elemacy-nav__link.elemacy-is-active';

        $this->start_controls_section(
            'section_style_submenu',
            [
                'label' => esc_html__('Dropdown', 'elemacy'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'panel_min_width',
            [
                'label' => esc_html__('Panel Minimum Width', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem', 'custom'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 1000,
                    ],
                ],
                'default' => [
                    'size' => 200,
                    'unit' => 'px',
                ],
                'selectors' => [$sub_selector => 'min-width: {{SIZE}}{{UNIT}}'],
            ]
        );

        $this->add_control(
            'panel_background_color',
            [
                'label' => esc_html__('Panel Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [$sub_selector => 'background-color: {{VALUE}}'],
                'default' => '#222222',
            ]
        );

        $this->add_responsive_control(
            'submenu_padding',
            [
                'label' => esc_html__('Panel Padding', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem', 'custom'],
                'selectors' => [$sub_selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
                'default' => [
                    'top' => 10,
                    'right' => 10,
                    'bottom' => 10,
                    'left' => 10,
                    'unit' => 'px',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'submenu_border',
                'selector' => $sub_selector
            ]
        );

        $this->add_responsive_control(
            'submenu_border_radius',
            [
                'label' => esc_html__('Border Radius', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em', 'rem', 'custom'],
                'selectors' => [$sub_selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'submenu_box_shadow',
                'selector' => $sub_selector
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'submenu_typography',
                'selector' => $sub_links,
                'separator' => 'before',
            ]
        );

        $this->start_controls_tabs('tabs_submenu');
        $this->start_controls_tab('tab_submenu_normal', ['label' => esc_html__('Normal', 'elemacy')]);
        $this->add_control(
            'submenu_color',
            ['label' => esc_html__('Text Color', 'elemacy'), 'type' => Controls_Manager::COLOR, 'selectors' => [$sub_links => 'color: {{VALUE}}']]
        );
        $this->add_control(
            'submenu_bg',
            ['label' => esc_html__('Background Color', 'elemacy'), 'type' => Controls_Manager::COLOR, 'selectors' => [$sub_links => 'background-color: {{VALUE}}']]
        );
        $this->end_controls_tab();

        $this->start_controls_tab('tab_submenu_hover', ['label' => esc_html__('Hover', 'elemacy')]);
        $this->add_control(
            'submenu_color_hover',
            ['label' => esc_html__('Text Color', 'elemacy'), 'type' => Controls_Manager::COLOR, 'selectors' => [$sub_links_hover => 'color: {{VALUE}}']]
        );
        $this->add_control(
            'submenu_bg_hover',
            ['label' => esc_html__('Background Color', 'elemacy'), 'type' => Controls_Manager::COLOR, 'selectors' => [$sub_links_hover => 'background-color: {{VALUE}}']]
        );
        $this->end_controls_tab();

        $this->start_controls_tab('tab_submenu_active', ['label' => esc_html__('Active', 'elemacy')]);
        $this->add_control(
            'submenu_color_active',
            ['label' => esc_html__('Text Color', 'elemacy'), 'type' => Controls_Manager::COLOR, 'selectors' => [$sub_links_active => 'color: {{VALUE}}']]
        );
        $this->add_control(
            'submenu_bg_active',
            ['label' => esc_html__('Background Color', 'elemacy'), 'type' => Controls_Manager::COLOR, 'selectors' => [$sub_links_active => 'background-color: {{VALUE}}']]
        );
        $this->end_controls_tab();
        $this->end_controls_tabs();


        $this->add_responsive_control(
            'submenu_item_padding',
            [
                'label' => esc_html__('Item Padding', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem', 'custom'],
                'selectors' => [$sub_links => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Mobile toggle style controls.
     */
    protected function register_toggle_style_controls(): void
    {
        $this->start_controls_section(
            'section_style_toggle',
            [
                'label' => esc_html__('Toggle Button', 'elemacy'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_toggle' => 'yes',
                ],
            ]
        );

        $this->start_controls_tabs('tabs_toggle_colors');

        $this->start_controls_tab(
            'tab_toggle_normal',
            [
                'label' => esc_html__('Normal', 'elemacy'),
            ]
        );

        $this->add_control(
            'toggle_color',
            [
                'label' => esc_html__('Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle-icon' => 'color: {{VALUE}}',
                    '{{WRAPPER}} .elemacy-nav__toggle-label' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'toggle_background',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'toggle_border_color',
            [
                'label' => esc_html__('Border Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle' => 'border-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_toggle_hover',
            [
                'label' => esc_html__('Hover', 'elemacy'),
            ]
        );

        $this->add_control(
            'toggle_color_hover',
            [
                'label' => esc_html__('Icon Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle:hover .elemacy-nav__toggle-icon' => 'color: {{VALUE}}',
                    '{{WRAPPER}} .elemacy-nav__toggle:hover .elemacy-nav__toggle-label' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'toggle_background_hover',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle:hover' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'toggle_border_color_hover',
            [
                'label' => esc_html__('Border Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle:hover' => 'border-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'toggle_label_typography',
                'selector' => '{{WRAPPER}} .elemacy-nav__toggle',
            ]
        );

        $this->add_responsive_control(
            'toggle_padding',
            [
                'label' => esc_html__('Padding', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem', 'custom'],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'toggle_border_radius',
            [
                'label' => esc_html__('Border Radius', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em', 'rem', 'custom'],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'toggle_border_width',
            [
                'label' => esc_html__('Border Width', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em', 'rem', 'custom'],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Mobile toggle close button style controls.
     */
    protected function register_toggle_close_style_controls(): void
    {
        $this->start_controls_section(
            'section_style_toggle_close',
            [
                'label' => esc_html__('Toggle Close Button', 'elemacy'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_toggle' => 'yes',
                ],
            ]
        );

        $this->start_controls_tabs('tabs_toggle_close_colors');

        $this->start_controls_tab(
            'tab_toggle_close_normal',
            [
                'label' => esc_html__('Normal', 'elemacy'),
            ]
        );

        $this->add_control(
            'toggle_close_color',
            [
                'label' => esc_html__('Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle-close-icon' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'toggle_close_background',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle-close' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'toggle_close_border_color',
            [
                'label' => esc_html__('Border Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle-close' => 'border-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_toggle_close_hover',
            [
                'label' => esc_html__('Hover', 'elemacy'),
            ]
        );

        $this->add_control(
            'toggle_close_color_hover',
            [
                'label' => esc_html__('Icon Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle-close:hover .elemacy-nav__toggle-close-icon' => 'color: {{VALUE}}'
                ],
            ]
        );

        $this->add_control(
            'toggle_close_background_hover',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle-close:hover' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'toggle_close_border_color_hover',
            [
                'label' => esc_html__('Border Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle-close:hover' => 'border-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'toggle_close_typography',
                'selector' => '{{WRAPPER}} .elemacy-nav__toggle-close',
            ]
        );

        $this->add_responsive_control(
            'toggle_close_padding',
            [
                'label' => esc_html__('Padding', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem', 'custom'],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle-close' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'toggle_close_border_radius',
            [
                'label' => esc_html__('Border Radius', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em', 'rem', 'custom'],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle-close' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'toggle_close_border_width',
            [
                'label' => esc_html__('Border Width', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em', 'rem', 'custom'],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle-close' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'toggle_close_top',
            [
                'label' => esc_html__('Top', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%', 'custom'],
                'range' => [
                    'px' => [
                        'min' => -500,
                        'max' => 500,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle-close' => 'top: {{SIZE}}{{UNIT}};',
                ],
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'toggle_close_right',
            [
                'label' => esc_html__('Right', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%', 'custom'],
                'range' => [
                    'px' => [
                        'min' => -500,
                        'max' => 500,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle-close' => 'right: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output on the frontend.
     */
    protected function render()
    {
        $menus = $this->get_available_menus();

        if (empty($menus)) {
            return;
        }

        $settings = $this->get_settings_for_display();

        if (empty($settings['menu'])) {
            return;
        }

        $menu_id_attr = 'elemacy-nav-menu-' . $this->get_id();

        $args = [
            'echo' => false,
            'menu' => $settings['menu'],
            'menu_class' => 'elemacy-nav__list',
            'container' => '',
            'fallback_cb' => '__return_empty_string',
            'walker' => new NavMenuWalker(),
        ];

        $menu_html = wp_nav_menu($args);

        if (empty($menu_html)) {
            return;
        }

        $wrapper_classes = [
            'elemacy-nav',
            'elemacy-nav--layout-' . esc_attr($settings['layout']),
        ];

        $breakpoint = !empty($settings['mobile_breakpoint']) ? $settings['mobile_breakpoint'] : 'tablet';
        $wrapper_classes[] = 'elemacy-nav--breakpoint-' . esc_attr($breakpoint);

        $this->add_render_attribute('wrapper', 'class', $wrapper_classes);

        $this->add_render_attribute('nav', 'class', 'elemacy-nav__menu');
        $this->add_render_attribute('nav', 'id', $menu_id_attr);

        $show_toggle = isset($settings['show_toggle']) && 'yes' === $settings['show_toggle'];
        ?>
        <div <?php $this->print_render_attribute_string('wrapper'); ?>>
            <div class="elemacy-nav__inner">
                <?php if ($show_toggle): ?>
                    <button class="elemacy-nav__toggle" type="button" aria-expanded="false"
                        aria-controls="<?php echo esc_attr($menu_id_attr); ?>">
                        <span class="elemacy-nav__toggle-icon" aria-hidden="true">
                            <?php
                            Icons_Manager::render_icon($settings['toggle_open_icon'], [
                                'aria-hidden' => 'true',
                            ]);
                            ?>
                        </span>
                        <?php if (isset($settings['show_toggle_label']) && 'yes' === $settings['show_toggle_label'] && !empty($settings['toggle_label'])): ?>
                            <span class="elemacy-nav__toggle-label">
                                <?php echo esc_html($settings['toggle_label']); ?>
                            </span>
                        <?php endif; ?>
                    </button>

                    <button class="elemacy-nav__toggle-close" type="button" aria-expanded="false"
                        aria-controls="<?php echo esc_attr($menu_id_attr); ?>">
                        <span class="elemacy-nav__toggle-close-icon" aria-hidden="true">
                            <?php
                            Icons_Manager::render_icon($settings['toggle_close_icon'], [
                                'aria-hidden' => 'true',
                            ]);
                            ?>
                        </span>
                    </button>
                <?php endif; ?>

                <nav <?php $this->print_render_attribute_string('nav'); ?>>
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $menu_html;
                    ?>
                </nav>
            </div>
        </div>
        <?php
    }
}

