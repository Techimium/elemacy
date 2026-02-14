<?php

namespace Elemacy\Modules\Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

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
        $this->register_dropdown_style_controls();
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
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'menu_label',
            [
                'label'       => esc_html__('Menu Label (for accessibility)', 'elemacy'),
                'type'        => Controls_Manager::TEXT,
                'placeholder' => esc_html__('Main navigation', 'elemacy'),
                'default'     => esc_html__('Main navigation', 'elemacy'),
            ]
        );

        $menus = $this->get_available_menus();

        if (!empty($menus)) {
            $this->add_control(
                'menu',
                [
                    'label'        => esc_html__('Menu', 'elemacy'),
                    'type'         => Controls_Manager::SELECT,
                    'options'      => $menus,
                    'default'      => array_keys($menus)[0],
                    'save_default' => true,
                    'description'  => sprintf(
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
                    'type'        => Controls_Manager::ALERT,
                    'alert_type'  => 'info',
                    'heading'     => esc_html__('No menus found', 'elemacy'),
                    'content'     => sprintf(
                        /* translators: 1: Link opening tag, 2: Link closing tag. */
                        esc_html__('Go to the %1$sMenus screen%2$s to create one.', 'elemacy'),
                        sprintf('<a href="%s" target="_blank">', esc_url(admin_url('nav-menus.php?action=edit&menu=0'))),
                        '</a>'
                    ),
                    'separator'   => 'after',
                ]
            );
        }

        $this->add_control(
            'layout',
            [
                'label'   => esc_html__('Layout', 'elemacy'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'horizontal',
                'options' => [
                    'horizontal' => esc_html__('Horizontal', 'elemacy'),
                    'vertical'   => esc_html__('Vertical', 'elemacy'),
                    'stacked'    => esc_html__('Stacked (Full Width)', 'elemacy'),
                ],
            ]
        );

        $this->add_responsive_control(
            'alignment',
            [
                'label'     => esc_html__('Alignment', 'elemacy'),
                'type'      => Controls_Manager::CHOOSE,
                'options'   => [
                    'flex-start' => [
                        'title' => esc_html__('Start', 'elemacy'),
                        'icon'  => 'eicon-align-start-h',
                    ],
                    'center'     => [
                        'title' => esc_html__('Center', 'elemacy'),
                        'icon'  => 'eicon-align-center-h',
                    ],
                    'flex-end'   => [
                        'title' => esc_html__('End', 'elemacy'),
                        'icon'  => 'eicon-align-end-h',
                    ],
                    'space-between' => [
                        'title' => esc_html__('Justify', 'elemacy'),
                        'icon'  => 'eicon-align-stretch-h',
                    ],
                ],
                'default'   => 'flex-start',
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__list' => 'justify-content: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'mobile_breakpoint',
            [
                'label'   => esc_html__('Mobile Breakpoint', 'elemacy'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'tablet',
                'options' => [
                    'none'   => esc_html__('None (always expanded)', 'elemacy'),
                    'mobile' => esc_html__('Mobile', 'elemacy'),
                    'tablet' => esc_html__('Tablet & below', 'elemacy'),
                ],
            ]
        );

        $this->add_control(
            'show_toggle',
            [
                'label'        => esc_html__('Show Toggle on Mobile', 'elemacy'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Yes', 'elemacy'),
                'label_off'    => esc_html__('No', 'elemacy'),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'toggle_label',
            [
                'label'     => esc_html__('Toggle Label', 'elemacy'),
                'type'      => Controls_Manager::TEXT,
                'default'   => esc_html__('Menu', 'elemacy'),
                'condition' => [
                    'show_toggle' => 'yes',
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
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'menu_typography',
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
                'label'     => esc_html__('Text Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__menu a.elemacy-nav__link' => 'color: {{VALUE}}',
                    '{{WRAPPER}} .elemacy-nav__submenu-toggle' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'menu_background',
            [
                'label'     => esc_html__('Background Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
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
                'label'     => esc_html__('Text Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__menu a.elemacy-nav__link:hover,
                     {{WRAPPER}} .elemacy-nav__menu a.elemacy-nav__link:focus,
                     {{WRAPPER}} .elemacy-nav__submenu-toggle:hover,
                     {{WRAPPER}} .elemacy-nav__submenu-toggle:focus' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'menu_background_hover',
            [
                'label'     => esc_html__('Background Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
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
                'label'     => esc_html__('Text Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__menu a.elemacy-nav__link.elemacy-is-active' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'menu_background_active',
            [
                'label'     => esc_html__('Background Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
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
                'label'     => esc_html__('Item Gap', 'elemacy'),
                'type'      => Controls_Manager::SLIDER,
                'size_units'=> ['px', 'em', 'rem', 'custom'],
                'range'     => [
                    'px' => [
                        'min' => 0,
                        'max' => 60,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__list' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'menu_item_padding',
            [
                'label'      => esc_html__('Item Padding', 'elemacy'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem', 'custom'],
                'selectors'  => [
                    '{{WRAPPER}} .elemacy-nav__menu a.elemacy-nav__link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'menu_item_border',
                'selector' => '{{WRAPPER}} .elemacy-nav__menu a.elemacy-nav__link',
            ]
        );

        $this->add_responsive_control(
            'menu_item_border_radius',
            [
                'label'      => esc_html__('Item Border Radius', 'elemacy'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em', 'rem', 'custom'],
                'selectors'  => [
                    '{{WRAPPER}} .elemacy-nav__menu a.elemacy-nav__link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Submenu (hover dropdown) style – nested items on desktop.
     */
    protected function register_submenu_style_controls(): void
    {
        $sub_selector = '{{WRAPPER}} .elemacy-nav__submenu';
        $sub_links = '{{WRAPPER}} .elemacy-nav__submenu .elemacy-nav__link';

        $this->start_controls_section(
            'section_style_submenu',
            [
                'label' => esc_html__('Submenu (hover)', 'elemacy'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'submenu_typography',
                'selector' => $sub_links,
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
            ['label' => esc_html__('Background Color', 'elemacy'), 'type' => Controls_Manager::COLOR, 'selectors' => [$sub_selector => 'background-color: {{VALUE}}']]
        );
        $this->end_controls_tab();

        $this->start_controls_tab('tab_submenu_hover', ['label' => esc_html__('Hover', 'elemacy')]);
        $this->add_control(
            'submenu_color_hover',
            ['label' => esc_html__('Text Color', 'elemacy'), 'type' => Controls_Manager::COLOR, 'selectors' => [$sub_links . ':hover, ' . $sub_links . ':focus' => 'color: {{VALUE}}']]
        );
        $this->add_control(
            'submenu_bg_hover',
            ['label' => esc_html__('Background Color', 'elemacy'), 'type' => Controls_Manager::COLOR, 'selectors' => [$sub_links . ':hover, ' . $sub_links . ':focus' => 'background-color: {{VALUE}}']]
        );
        $this->end_controls_tab();

        $this->start_controls_tab('tab_submenu_active', ['label' => esc_html__('Active', 'elemacy')]);
        $this->add_control(
            'submenu_color_active',
            ['label' => esc_html__('Text Color', 'elemacy'), 'type' => Controls_Manager::COLOR, 'selectors' => [$sub_links . '.elemacy-is-active' => 'color: {{VALUE}}']]
        );
        $this->add_control(
            'submenu_bg_active',
            ['label' => esc_html__('Background Color', 'elemacy'), 'type' => Controls_Manager::COLOR, 'selectors' => [$sub_links . '.elemacy-is-active' => 'background-color: {{VALUE}}']]
        );
        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_responsive_control(
            'submenu_padding',
            [
                'label'      => esc_html__('Panel Padding', 'elemacy'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem', 'custom'],
                'selectors'  => [$sub_selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
                'separator'  => 'before',
            ]
        );
        $this->add_responsive_control(
            'submenu_item_padding',
            [
                'label'      => esc_html__('Item Padding', 'elemacy'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem', 'custom'],
                'selectors'  => [$sub_links => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );
        $this->add_group_control(
            Group_Control_Border::get_type(),
            ['name' => 'submenu_border', 'selector' => $sub_selector]
        );
        $this->add_responsive_control(
            'submenu_border_radius',
            [
                'label'      => esc_html__('Border Radius', 'elemacy'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em', 'rem', 'custom'],
                'selectors'  => [$sub_selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );
        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            ['name' => 'submenu_box_shadow', 'selector' => $sub_selector]
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
                'label'     => esc_html__('Toggle Button', 'elemacy'),
                'tab'       => Controls_Manager::TAB_STYLE,
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
                'label'     => esc_html__('Icon Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle-line' => 'background-color: {{VALUE}}',
                    '{{WRAPPER}} .elemacy-nav__toggle-label' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'toggle_background',
            [
                'label'     => esc_html__('Background Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle' => 'background-color: {{VALUE}}',
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
                'label'     => esc_html__('Icon Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle:hover .elemacy-nav__toggle-line' => 'background-color: {{VALUE}}',
                    '{{WRAPPER}} .elemacy-nav__toggle:hover .elemacy-nav__toggle-label' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'toggle_background_hover',
            [
                'label'     => esc_html__('Background Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav__toggle:hover' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'toggle_label_typography',
                'selector' => '{{WRAPPER}} .elemacy-nav__toggle-label',
            ]
        );

        $this->add_responsive_control(
            'toggle_padding',
            [
                'label'      => esc_html__('Padding', 'elemacy'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem', 'custom'],
                'selectors'  => [
                    '{{WRAPPER}} .elemacy-nav__toggle' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'separator'  => 'before',
            ]
        );

        $this->add_responsive_control(
            'toggle_border_radius',
            [
                'label'      => esc_html__('Border Radius', 'elemacy'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em', 'rem', 'custom'],
                'selectors'  => [
                    '{{WRAPPER}} .elemacy-nav__toggle' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'toggle_icon_size',
            [
                'label'     => esc_html__('Icon Size', 'elemacy'),
                'type'      => Controls_Manager::SLIDER,
                'size_units'=> ['px', 'em', 'rem', 'custom'],
                'range'     => [
                    'px' => [
                        'min' => 10,
                        'max' => 40,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-nav' => '--elemacy-nav-toggle-size: {{SIZE}}{{UNIT}}',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Dropdown panel style (menu shown below toggle on mobile/tablet).
     */
    protected function register_dropdown_style_controls(): void
    {
        /* Target dropdown panel (menu below toggle). WRAPPER is Elementor widget root; .elemacy-nav is our inner wrapper. */
        $dropdown_selector = '{{WRAPPER}} .elemacy-nav.elemacy-nav--breakpoint-mobile .elemacy-nav__menu, {{WRAPPER}} .elemacy-nav.elemacy-nav--breakpoint-tablet .elemacy-nav__menu';
        $dropdown_links = '{{WRAPPER}} .elemacy-nav.elemacy-nav--breakpoint-mobile .elemacy-nav__menu .elemacy-nav__link, {{WRAPPER}} .elemacy-nav.elemacy-nav--breakpoint-tablet .elemacy-nav__menu .elemacy-nav__link';
        $dropdown_links_hover = '{{WRAPPER}} .elemacy-nav.elemacy-nav--breakpoint-mobile .elemacy-nav__menu .elemacy-nav__link:hover, {{WRAPPER}} .elemacy-nav.elemacy-nav--breakpoint-mobile .elemacy-nav__menu .elemacy-nav__link:focus, {{WRAPPER}} .elemacy-nav.elemacy-nav--breakpoint-tablet .elemacy-nav__menu .elemacy-nav__link:hover, {{WRAPPER}} .elemacy-nav.elemacy-nav--breakpoint-tablet .elemacy-nav__menu .elemacy-nav__link:focus';
        $dropdown_links_active = '{{WRAPPER}} .elemacy-nav.elemacy-nav--breakpoint-mobile .elemacy-nav__menu .elemacy-nav__link.elemacy-is-active, {{WRAPPER}} .elemacy-nav.elemacy-nav--breakpoint-tablet .elemacy-nav__menu .elemacy-nav__link.elemacy-is-active';

        $this->start_controls_section(
            'section_style_dropdown',
            [
                'label'     => esc_html__('Dropdown', 'elemacy'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_toggle' => 'yes',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'dropdown_typography',
                'selector' => $dropdown_links,
            ]
        );

        $this->start_controls_tabs('tabs_dropdown');

        $this->start_controls_tab(
            'tab_dropdown_normal',
            ['label' => esc_html__('Normal', 'elemacy')]
        );
        $this->add_control(
            'dropdown_color',
            [
                'label'     => esc_html__('Text Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [$dropdown_links => 'color: {{VALUE}}'],
            ]
        );
        $this->add_control(
            'dropdown_bg',
            [
                'label'     => esc_html__('Background Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [$dropdown_selector => 'background-color: {{VALUE}}'],
            ]
        );
        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_dropdown_hover',
            ['label' => esc_html__('Hover', 'elemacy')]
        );
        $this->add_control(
            'dropdown_color_hover',
            [
                'label'     => esc_html__('Text Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [$dropdown_links_hover => 'color: {{VALUE}}'],
            ]
        );
        $this->add_control(
            'dropdown_bg_hover',
            [
                'label'     => esc_html__('Background Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [$dropdown_links_hover => 'background-color: {{VALUE}}'],
            ]
        );
        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_dropdown_active',
            ['label' => esc_html__('Active', 'elemacy')]
        );
        $this->add_control(
            'dropdown_color_active',
            [
                'label'     => esc_html__('Text Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [$dropdown_links_active => 'color: {{VALUE}}'],
            ]
        );
        $this->add_control(
            'dropdown_bg_active',
            [
                'label'     => esc_html__('Background Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [$dropdown_links_active => 'background-color: {{VALUE}}'],
            ]
        );
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control(
            'dropdown_padding',
            [
                'label'      => esc_html__('Panel Padding', 'elemacy'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem', 'custom'],
                'selectors'  => [
                    $dropdown_selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'separator'  => 'before',
            ]
        );
        $this->add_responsive_control(
            'dropdown_item_padding',
            [
                'label'      => esc_html__('Item Padding', 'elemacy'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem', 'custom'],
                'selectors'  => [
                    $dropdown_links => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'dropdown_border',
                'selector' => $dropdown_selector,
            ]
        );
        $this->add_responsive_control(
            'dropdown_border_radius',
            [
                'label'      => esc_html__('Border Radius', 'elemacy'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em', 'rem', 'custom'],
                'selectors'  => [
                    $dropdown_selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'dropdown_box_shadow',
                'selector' => $dropdown_selector,
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
            'echo'        => false,
            'menu'        => $settings['menu'],
            'menu_class'  => 'elemacy-nav__list',
            'container'   => '',
            'fallback_cb' => '__return_empty_string',
        ];

        // Add custom filters to adjust classes.
        add_filter('nav_menu_link_attributes', [$this, 'filter_link_attributes'], 10, 4);
        add_filter('nav_menu_css_class', [$this, 'filter_menu_item_classes'], 10, 4);
        add_filter('nav_menu_submenu_css_class', [$this, 'filter_submenu_classes'], 10, 3);
        add_filter('nav_menu_item_id', '__return_empty_string');

        $menu_html = wp_nav_menu($args);

        // Remove custom filters to avoid side effects.
        remove_filter('nav_menu_link_attributes', [$this, 'filter_link_attributes'], 10);
        remove_filter('nav_menu_css_class', [$this, 'filter_menu_item_classes'], 10);
        remove_filter('nav_menu_submenu_css_class', [$this, 'filter_submenu_classes'], 10);
        remove_filter('nav_menu_item_id', '__return_empty_string');

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

        if (!empty($settings['menu_label'])) {
            $this->add_render_attribute('nav', 'aria-label', esc_attr($settings['menu_label']));
        }

        $this->add_render_attribute('nav', 'class', 'elemacy-nav__menu');
        $this->add_render_attribute('nav', 'id', $menu_id_attr);

        $show_toggle = isset($settings['show_toggle']) && 'yes' === $settings['show_toggle'];
        ?>
        <div <?php $this->print_render_attribute_string('wrapper'); ?>>
            <div class="elemacy-nav__inner">
                <?php if ($show_toggle) : ?>
                    <button class="elemacy-nav__toggle" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr($menu_id_attr); ?>">
                        <span class="elemacy-nav__toggle-icon" aria-hidden="true">
                            <span class="elemacy-nav__toggle-line"></span>
                            <span class="elemacy-nav__toggle-line"></span>
                            <span class="elemacy-nav__toggle-line"></span>
                        </span>
                        <?php if (!empty($settings['toggle_label'])) : ?>
                            <span class="elemacy-nav__toggle-label">
                                <?php echo esc_html($settings['toggle_label']); ?>
                            </span>
                        <?php endif; ?>
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

    /**
     * Add clean, predictable classes to menu links.
     *
     * @param array  $atts
     * @param \WP_Post $item
     * @param array  $args
     * @param int    $depth
     *
     * @return array
     */
    public function filter_link_attributes($atts, $item, $args, $depth)
    {
        $classes = 'elemacy-nav__link';

        if (in_array('current-menu-item', (array) $item->classes, true)) {
            $classes .= ' elemacy-is-active';
        }

        if (empty($atts['class'])) {
            $atts['class'] = $classes;
        } else {
            $atts['class'] .= ' ' . $classes;
        }

        return $atts;
    }

    /**
     * Add BEM-style classes to menu list items.
     *
     * @param array   $classes
     * @param \WP_Post $item
     * @param array   $args
     * @param int     $depth
     *
     * @return array
     */
    public function filter_menu_item_classes($classes, $item, $args, $depth)
    {
        $classes[] = 'elemacy-nav__item';

        if (!empty($depth)) {
            $classes[] = 'elemacy-nav__item--depth-' . (int) $depth;
        }

        if (in_array('menu-item-has-children', (array) $classes, true)) {
            $classes[] = 'elemacy-nav__item--has-children';
        }

        return $classes;
    }

    /**
     * Add BEM class to submenu ul for styling (hover dropdown).
     *
     * @param array   $classes
     * @param \stdClass $args
     * @param int     $depth
     * @return array
     */
    public function filter_submenu_classes($classes, $args, $depth)
    {
        $classes[] = 'elemacy-nav__submenu';
        return $classes;
    }
}

