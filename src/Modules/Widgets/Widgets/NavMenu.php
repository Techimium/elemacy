<?php

namespace Elemacy\Modules\Widgets\Widgets;

use Elemacy\Modules\Widgets\Walkers\NavMenuWalker;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Icons_Manager;

if (!defined('ABSPATH')) {
    exit;
}

class NavMenu extends BaseWidget
{
    public function get_name(): string
    {
        return 'elemacy_nav_menu';
    }
    public function get_title(): string
    {
        return esc_html__('Nav Menu', 'elemacy');
    }
    public function get_icon(): string
    {
        return 'eicon-nav-menu';
    }
    public function get_keywords(): array
    {
        return ['nav', 'menu', 'navigation', 'elemacy'];
    }
    public function get_style_depends(): array
    {
        return ['elemacy-nav-menu'];
    }
    public function get_script_depends(): array
    {
        return ['elemacy-nav-menu'];
    }

    protected function get_available_menus(): array
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

    protected function register_controls(): void
    {
        $this->register_layout_controls();
        $this->register_main_menu_style_controls();
        $this->register_submenu_style_controls();
        $this->register_overlay_style_controls();
        $this->register_toggle_style_controls();
        $this->register_toggle_close_style_controls();
    }

    // =========================================================================
    // Content Tab
    // =========================================================================

    protected function register_layout_controls(): void
    {
        $this->start_controls_section('section_layout', [
            'label' => esc_html__('Layout', 'elemacy'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $menus = $this->get_available_menus();

        if (!empty($menus)) {
            $this->add_control('menu', [
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
            ]);
        } else {
            $this->add_control('menu', [
                'type'       => Controls_Manager::ALERT,
                'alert_type' => 'info',
                'heading'    => esc_html__('No menus found', 'elemacy'),
                'content'    => sprintf(
                    /* translators: 1: Link opening tag, 2: Link closing tag. */
                    esc_html__('Go to the %1$sMenus screen%2$s to create one.', 'elemacy'),
                    sprintf('<a href="%s" target="_blank">', esc_url(admin_url('nav-menus.php?action=edit&menu=0'))),
                    '</a>'
                ),
                'separator' => 'after',
            ]);
        }

        $this->add_control('layout', [
            'label'   => esc_html__('Layout', 'elemacy'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'horizontal',
            'options' => [
                'horizontal' => esc_html__('Horizontal', 'elemacy'),
                'vertical'   => esc_html__('Vertical', 'elemacy'),
                'stacked'    => esc_html__('Stacked (Full Width)', 'elemacy'),
            ],
        ]);

        $this->add_responsive_control('alignment', [
            'label'     => esc_html__('Alignment', 'elemacy'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'flex-start'    => ['title' => esc_html__('Start', 'elemacy'),   'icon' => 'eicon-align-start-h'],
                'center'        => ['title' => esc_html__('Center', 'elemacy'),  'icon' => 'eicon-align-center-h'],
                'flex-end'      => ['title' => esc_html__('End', 'elemacy'),     'icon' => 'eicon-align-end-h'],
                'space-between' => ['title' => esc_html__('Justify', 'elemacy'), 'icon' => 'eicon-align-stretch-h'],
            ],
            'default'   => 'flex-start',
            'selectors' => ['{{WRAPPER}} .elemacy-nav__list' => 'justify-content: {{VALUE}};'],
        ]);

        $this->add_control('mobile_breakpoint', [
            'label'     => esc_html__('Mobile Breakpoint', 'elemacy'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'tablet',
            'options'   => [
                'none'   => esc_html__('None (always expanded)', 'elemacy'),
                'mobile' => esc_html__('Mobile', 'elemacy'),
                'tablet' => esc_html__('Tablet & below', 'elemacy'),
            ],
            'separator' => 'before',
        ]);

        $this->add_control('overlay_type', [
            'label'     => esc_html__('Overlay Type', 'elemacy'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'fullscreen',
            'options'   => [
                'fullscreen'  => esc_html__('Fullscreen', 'elemacy'),
                'slide-right' => esc_html__('Slide from Right', 'elemacy'),
                'slide-left'  => esc_html__('Slide from Left', 'elemacy'),
            ],
            'condition' => ['mobile_breakpoint!' => 'none'],
        ]);

        $this->add_responsive_control('overlay_panel_width', [
            'label'      => esc_html__('Panel Width', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%', 'vw', 'custom'],
            'range'      => ['px' => ['min' => 200, 'max' => 800]],
            'default'    => ['size' => 320, 'unit' => 'px'],
            'selectors'  => ['{{WRAPPER}} .elemacy-nav' => '--elemacy-overlay-panel-width: {{SIZE}}{{UNIT}};'],
            'condition'  => [
                'overlay_type'        => ['slide-right', 'slide-left'],
                'mobile_breakpoint!' => 'none',
            ],
        ]);

        $this->add_control('show_toggle', [
            'label'        => esc_html__('Show Toggle Button', 'elemacy'),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => esc_html__('Yes', 'elemacy'),
            'label_off'    => esc_html__('No', 'elemacy'),
            'return_value' => 'yes',
            'default'      => 'yes',
            'separator'    => 'before',
        ]);

        $this->add_control('show_toggle_label', [
            'label'        => esc_html__('Show Toggle Label', 'elemacy'),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => esc_html__('Yes', 'elemacy'),
            'label_off'    => esc_html__('No', 'elemacy'),
            'return_value' => 'yes',
            'default'      => 'no',
            'condition'    => ['show_toggle' => 'yes'],
        ]);

        $this->add_control('toggle_label', [
            'label'     => esc_html__('Toggle Label', 'elemacy'),
            'type'      => Controls_Manager::TEXT,
            'default'   => 'Menu',
            'condition' => ['show_toggle_label' => 'yes'],
        ]);

        $this->add_responsive_control('toggle_alignment', [
            'label'     => esc_html__('Toggle Alignment', 'elemacy'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'start'  => ['title' => esc_html__('Start', 'elemacy'),  'icon' => 'eicon-align-start-h'],
                'center' => ['title' => esc_html__('Center', 'elemacy'), 'icon' => 'eicon-align-center-h'],
                'end'    => ['title' => esc_html__('End', 'elemacy'),    'icon' => 'eicon-align-end-h'],
            ],
            'default'   => 'end',
            'selectors' => ['{{WRAPPER}} .elemacy-nav__inner' => 'justify-content: {{VALUE}};'],
            'condition' => ['show_toggle' => 'yes'],
        ]);

        $this->add_control('toggle_open_icon', [
            'label'   => esc_html__('Toggle Icon', 'elemacy'),
            'type'    => Controls_Manager::ICONS,
            'default' => ['value' => 'eicon-menu-bar', 'library' => 'eicon'],
        ]);

        $this->add_control('toggle_close_icon', [
            'label'   => esc_html__('Close Icon', 'elemacy'),
            'type'    => Controls_Manager::ICONS,
            'default' => ['value' => 'eicon-close', 'library' => 'eicon'],
        ]);

        $this->end_controls_section();
    }

    // =========================================================================
    // Style Tab
    // =========================================================================

    protected function register_main_menu_style_controls(): void
    {
        $nav      = '{{WRAPPER}} .elemacy-nav:not(.is-open)';
        $link     = $nav . ' .elemacy-nav__list > .elemacy-nav__item > .elemacy-nav__link';
        $link_hov = $nav . ' .elemacy-nav__list > .elemacy-nav__item > .elemacy-nav__link:hover, ' .
            $nav . ' .elemacy-nav__list > .elemacy-nav__item > .elemacy-nav__link:focus';
        $link_act = $nav . ' .elemacy-nav__list > .elemacy-nav__item > .elemacy-nav__link.elemacy-is-active';
        $list     = $nav . ' .elemacy-nav__list';

        $this->start_controls_section('section_style_main_menu', [
            'label' => esc_html__('Main Menu', 'elemacy'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'menu_typography',
            'selector' => $link,
        ]);

        $this->start_controls_tabs('tabs_menu_colors');

        $this->start_controls_tab('tab_menu_normal', ['label' => esc_html__('Normal', 'elemacy')]);
        $this->add_control('menu_color', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$link => 'color: {{VALUE}}'],
        ]);
        $this->add_control('menu_background', [
            'label'     => esc_html__('Background', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$link => 'background-color: {{VALUE}}'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('tab_menu_hover', ['label' => esc_html__('Hover', 'elemacy')]);
        $this->add_control('menu_color_hover', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$link_hov => 'color: {{VALUE}}'],
        ]);
        $this->add_control('menu_background_hover', [
            'label'     => esc_html__('Background', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$link_hov => 'background-color: {{VALUE}}'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('tab_menu_active', ['label' => esc_html__('Active', 'elemacy')]);
        $this->add_control('menu_color_active', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$link_act => 'color: {{VALUE}}'],
        ]);
        $this->add_control('menu_background_active', [
            'label'     => esc_html__('Background', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$link_act => 'background-color: {{VALUE}}'],
        ]);
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control('menu_item_gap', [
            'label'      => esc_html__('Item Gap', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'range'      => ['px' => ['min' => 0, 'max' => 80]],
            'default'    => ['size' => 20, 'unit' => 'px'],
            'selectors'  => [$list => 'gap: {{SIZE}}{{UNIT}};'],
            'separator'  => 'before',
        ]);

        $this->add_responsive_control('menu_item_padding', [
            'label'      => esc_html__('Item Padding', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'selectors'  => [$link => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'menu_item_border',
            'selector' => $link,
        ]);

        $this->add_responsive_control('menu_item_border_radius', [
            'label'      => esc_html__('Border Radius', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em', 'rem', 'custom'],
            'selectors'  => [$link => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'menu_item_box_shadow',
            'selector' => $link,
        ]);

        $this->add_control('pointer_heading', [
            'label'     => esc_html__('Pointer Indicator', 'elemacy'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('pointer', [
            'label'   => esc_html__('Type', 'elemacy'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'none',
            'options' => [
                'none'      => esc_html__('None', 'elemacy'),
                'underline' => esc_html__('Underline', 'elemacy'),
                'overline'  => esc_html__('Overline', 'elemacy'),
                'double'    => esc_html__('Double Line', 'elemacy'),
                'framed'    => esc_html__('Framed', 'elemacy'),
            ],
        ]);

        $this->add_control('pointer_color', [
            'label'     => esc_html__('Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .elemacy-nav' => '--elemacy-pointer-color: {{VALUE}};'],
            'condition' => ['pointer!' => 'none'],
        ]);

        $this->add_responsive_control('pointer_width', [
            'label'      => esc_html__('Thickness', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'custom'],
            'range'      => ['px' => ['min' => 1, 'max' => 10]],
            'default'    => ['size' => 2, 'unit' => 'px'],
            'selectors'  => ['{{WRAPPER}} .elemacy-nav' => '--elemacy-pointer-height: {{SIZE}}{{UNIT}};'],
            'condition'  => ['pointer!' => 'none'],
        ]);

        $this->add_control('pointer_animation', [
            'label'     => esc_html__('Animation', 'elemacy'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'slide',
            'options'   => [
                'slide' => esc_html__('Slide', 'elemacy'),
                'grow'  => esc_html__('Grow', 'elemacy'),
                'fade'  => esc_html__('Fade', 'elemacy'),
            ],
            'condition' => ['pointer' => ['underline', 'overline']],
        ]);

        $this->add_responsive_control('pointer_duration', [
            'label'     => esc_html__('Duration (ms)', 'elemacy'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => 50, 'max' => 1000]],
            'default'   => ['size' => 300, 'unit' => 'px'],
            'selectors' => ['{{WRAPPER}} .elemacy-nav' => '--elemacy-pointer-duration: {{SIZE}}ms;'],
            'condition' => ['pointer!' => 'none'],
        ]);

        $this->end_controls_section();
    }

    protected function register_overlay_style_controls(): void
    {
        $open     = '{{WRAPPER}} .elemacy-nav.is-open';
        $menu     = $open . ' .elemacy-nav__menu';
        $list     = $open . ' .elemacy-nav__list';
        $link     = $open . ' .elemacy-nav__list > .elemacy-nav__item > .elemacy-nav__link';
        $link_hov = $open . ' .elemacy-nav__list > .elemacy-nav__item > .elemacy-nav__link:hover, ' .
            $open . ' .elemacy-nav__list > .elemacy-nav__item > .elemacy-nav__link:focus';
        $link_act = $open . ' .elemacy-nav__list > .elemacy-nav__item > .elemacy-nav__link.elemacy-is-active';
        $sub      = $open . ' .elemacy-nav__submenu';
        $sub_link = $open . ' .elemacy-nav__submenu .elemacy-nav__link';
        $sub_hov  = $open . ' .elemacy-nav__submenu .elemacy-nav__link:hover';
        $sub_act  = $open . ' .elemacy-nav__submenu .elemacy-nav__link.elemacy-is-active';

        $this->start_controls_section('section_style_overlay', [
            'label'     => esc_html__('Overlay Menu', 'elemacy'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['mobile_breakpoint!' => 'none'],
        ]);

        $this->add_control('overlay_box_heading', [
            'label' => esc_html__('Overlay Box', 'elemacy'),
            'type'  => Controls_Manager::HEADING,
        ]);

        $this->add_control('overlay_bg_color', [
            'label'     => esc_html__('Background Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .elemacy-nav.is-open' => '--elemacy-overlay-bg: {{VALUE}};'],
        ]);

        $this->add_control('overlay_backdrop_blur', [
            'label'      => esc_html__('Backdrop Blur', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'default'    => ['size' => 10, 'unit' => 'px'],
            'selectors'  => [$menu => 'backdrop-filter: blur({{SIZE}}px); -webkit-backdrop-filter: blur({{SIZE}}px);'],
        ]);

        $this->add_responsive_control('overlay_padding', [
            'label'      => esc_html__('Overlay Padding', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%', 'custom'],
            'selectors'  => [$menu => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'overlay_box_shadow',
            'selector' => $menu,
        ]);

        $this->add_control('overlay_items_heading', [
            'label'     => esc_html__('Menu Items', 'elemacy'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_responsive_control('overlay_items_align', [
            'label'     => esc_html__('Horizontal Alignment', 'elemacy'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'flex-start' => ['title' => esc_html__('Start', 'elemacy'),  'icon' => 'eicon-align-start-h'],
                'center'     => ['title' => esc_html__('Center', 'elemacy'), 'icon' => 'eicon-align-center-h'],
                'flex-end'   => ['title' => esc_html__('End', 'elemacy'),    'icon' => 'eicon-align-end-h'],
            ],
            'selectors' => ['{{WRAPPER}} .elemacy-nav.is-open' => '--elemacy-overlay-items-align: {{VALUE}};'],
        ]);

        $this->add_responsive_control('overlay_items_vertical_align', [
            'label'     => esc_html__('Vertical Position', 'elemacy'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'flex-start' => ['title' => esc_html__('Top', 'elemacy'),    'icon' => 'eicon-v-align-top'],
                'center'     => ['title' => esc_html__('Middle', 'elemacy'), 'icon' => 'eicon-v-align-middle'],
                'flex-end'   => ['title' => esc_html__('Bottom', 'elemacy'), 'icon' => 'eicon-v-align-bottom'],
            ],
            'selectors' => ['{{WRAPPER}} .elemacy-nav.is-open' => '--elemacy-overlay-menu-valign: {{VALUE}};'],
            'condition' => ['overlay_type' => 'fullscreen'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'      => 'overlay_menu_typography',
            'selector'  => $link,
            'separator' => 'before',
        ]);

        $this->start_controls_tabs('tabs_overlay_colors');

        $this->start_controls_tab('tab_overlay_normal', ['label' => esc_html__('Normal', 'elemacy')]);
        $this->add_control('overlay_menu_color', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$link => 'color: {{VALUE}}'],
        ]);
        $this->add_control('overlay_menu_background', [
            'label'     => esc_html__('Item Background', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$link => 'background-color: {{VALUE}}'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('tab_overlay_hover', ['label' => esc_html__('Hover', 'elemacy')]);
        $this->add_control('overlay_menu_color_hover', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$link_hov => 'color: {{VALUE}}'],
        ]);
        $this->add_control('overlay_menu_background_hover', [
            'label'     => esc_html__('Item Background', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$link_hov => 'background-color: {{VALUE}}'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('tab_overlay_active', ['label' => esc_html__('Active', 'elemacy')]);
        $this->add_control('overlay_menu_color_active', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$link_act => 'color: {{VALUE}}'],
        ]);
        $this->add_control('overlay_menu_background_active', [
            'label'     => esc_html__('Item Background', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$link_act => 'background-color: {{VALUE}}'],
        ]);
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control('overlay_item_gap', [
            'label'      => esc_html__('Item Gap', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'range'      => ['px' => ['min' => 0, 'max' => 80]],
            'selectors'  => [$list => 'gap: {{SIZE}}{{UNIT}};'],
            'separator'  => 'before',
        ]);

        $this->add_responsive_control('overlay_item_padding', [
            'label'      => esc_html__('Item Padding', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'selectors'  => [$link => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'overlay_item_border',
            'selector' => $link,
        ]);

        $this->add_responsive_control('overlay_item_border_radius', [
            'label'      => esc_html__('Item Border Radius', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em', 'rem', 'custom'],
            'selectors'  => [$link => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_control('overlay_sub_heading', [
            'label'     => esc_html__('Sub-menu Items', 'elemacy'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'overlay_sub_typography',
            'selector' => $sub_link,
        ]);

        $this->start_controls_tabs('tabs_overlay_sub_colors');

        $this->start_controls_tab('tab_overlay_sub_normal', ['label' => esc_html__('Normal', 'elemacy')]);
        $this->add_control('overlay_sub_color', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$sub_link => 'color: {{VALUE}}'],
        ]);
        $this->add_control('overlay_sub_background', [
            'label'     => esc_html__('Background', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$sub_link => 'background-color: {{VALUE}}'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('tab_overlay_sub_hover', ['label' => esc_html__('Hover', 'elemacy')]);
        $this->add_control('overlay_sub_color_hover', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$sub_hov => 'color: {{VALUE}}'],
        ]);
        $this->add_control('overlay_sub_background_hover', [
            'label'     => esc_html__('Background', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$sub_hov => 'background-color: {{VALUE}}'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('tab_overlay_sub_active', ['label' => esc_html__('Active', 'elemacy')]);
        $this->add_control('overlay_sub_color_active', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$sub_act => 'color: {{VALUE}}'],
        ]);
        $this->add_control('overlay_sub_background_active', [
            'label'     => esc_html__('Background', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$sub_act => 'background-color: {{VALUE}}'],
        ]);
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control('overlay_sub_gap', [
            'label'      => esc_html__('Sub-item Gap', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => [$sub => 'gap: {{SIZE}}{{UNIT}};'],
            'separator'  => 'before',
        ]);

        $this->add_responsive_control('overlay_sub_padding', [
            'label'      => esc_html__('Sub-item Padding', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'selectors'  => [$sub_link => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->end_controls_section();
    }

    protected function register_submenu_style_controls(): void
    {
        $nav        = '{{WRAPPER}} .elemacy-nav:not(.is-open)';
        $sub        = $nav . ' .elemacy-nav__list .elemacy-nav__item .elemacy-nav__submenu';
        $sub_links  = $sub . ' .elemacy-nav__link';
        $sub_hover  = $sub . ' .elemacy-nav__link:hover';
        $sub_active = $sub . ' .elemacy-nav__link.elemacy-is-active';

        $this->start_controls_section('section_style_submenu', [
            'label' => esc_html__('Dropdown', 'elemacy'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('panel_min_width', [
            'label'      => esc_html__('Panel Min Width', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'range'      => ['px' => ['min' => 0, 'max' => 1000]],
            'selectors'  => [$sub => 'min-width: {{SIZE}}{{UNIT}}'],
        ]);

        $this->add_control('panel_background_color', [
            'label'     => esc_html__('Panel Background', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$sub => 'background-color: {{VALUE}}'],
            'default'   => '#222222',
        ]);

        $this->add_responsive_control('submenu_padding', [
            'label'      => esc_html__('Panel Padding', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'selectors'  => [$sub => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            'default'    => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10, 'unit' => 'px'],
        ]);

        $this->add_responsive_control('submenu_offset', [
            'label'      => esc_html__('Vertical Offset', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'custom'],
            'range'      => ['px' => ['min' => -20, 'max' => 40]],
            'selectors'  => [$sub => 'margin-top: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'submenu_border',
            'selector' => $sub,
        ]);

        $this->add_responsive_control('submenu_border_radius', [
            'label'      => esc_html__('Border Radius', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em', 'rem', 'custom'],
            'selectors'  => [$sub => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'submenu_box_shadow',
            'selector' => $sub,
        ]);

        $this->add_control('show_submenu_arrow', [
            'label'                => esc_html__('Show Dropdown Arrow', 'elemacy'),
            'type'                 => Controls_Manager::SWITCHER,
            'return_value'         => 'yes',
            'default'              => 'yes',
            'separator'            => 'before',
            'selectors'            => [
                '{{WRAPPER}} .elemacy-nav:not(.is-open) .elemacy-nav__item--has-children > .elemacy-nav__link::after' => 'display: {{VALUE}};',
            ],
            'selectors_dictionary' => [
                'yes' => 'inline-block',
                ''    => 'none',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'      => 'submenu_typography',
            'selector'  => $sub_links,
            'separator' => 'before',
        ]);

        $this->start_controls_tabs('tabs_submenu');

        $this->start_controls_tab('tab_submenu_normal', ['label' => esc_html__('Normal', 'elemacy')]);
        $this->add_control('submenu_color', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$sub_links => 'color: {{VALUE}}'],
        ]);
        $this->add_control('submenu_bg', [
            'label'     => esc_html__('Background', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$sub_links => 'background-color: {{VALUE}}'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('tab_submenu_hover', ['label' => esc_html__('Hover', 'elemacy')]);
        $this->add_control('submenu_color_hover', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$sub_hover => 'color: {{VALUE}}'],
        ]);
        $this->add_control('submenu_bg_hover', [
            'label'     => esc_html__('Background', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$sub_hover => 'background-color: {{VALUE}}'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('tab_submenu_active', ['label' => esc_html__('Active', 'elemacy')]);
        $this->add_control('submenu_color_active', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$sub_active => 'color: {{VALUE}}'],
        ]);
        $this->add_control('submenu_bg_active', [
            'label'     => esc_html__('Background', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$sub_active => 'background-color: {{VALUE}}'],
        ]);
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control('submenu_item_gap', [
            'label'      => esc_html__('Item Gap', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => [$sub => 'gap: {{SIZE}}{{UNIT}};'],
            'separator'  => 'before',
        ]);

        $this->add_responsive_control('submenu_item_padding', [
            'label'      => esc_html__('Item Padding', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'selectors'  => [$sub_links => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'submenu_item_border',
            'selector' => $sub_links,
        ]);

        $this->add_responsive_control('submenu_item_border_radius', [
            'label'      => esc_html__('Item Border Radius', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em', 'rem', 'custom'],
            'selectors'  => [$sub_links => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'submenu_item_box_shadow',
            'selector' => $sub_links,
        ]);

        $this->end_controls_section();
    }

    protected function register_toggle_style_controls(): void
    {
        $toggle = '{{WRAPPER}} .elemacy-nav__toggle';

        $this->start_controls_section('section_style_toggle', [
            'label'     => esc_html__('Toggle Button', 'elemacy'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['show_toggle' => 'yes'],
        ]);

        $this->start_controls_tabs('tabs_toggle_colors');

        $this->start_controls_tab('tab_toggle_normal', ['label' => esc_html__('Normal', 'elemacy')]);
        $this->add_control('toggle_color', [
            'label'     => esc_html__('Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .elemacy-nav__toggle-icon'  => 'color: {{VALUE}}',
                '{{WRAPPER}} .elemacy-nav__toggle-label' => 'color: {{VALUE}}',
            ],
        ]);
        $this->add_control('toggle_background', [
            'label'     => esc_html__('Background', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$toggle => 'background-color: {{VALUE}}'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('tab_toggle_hover', ['label' => esc_html__('Hover', 'elemacy')]);
        $this->add_control('toggle_color_hover', [
            'label'     => esc_html__('Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .elemacy-nav__toggle:hover .elemacy-nav__toggle-icon'  => 'color: {{VALUE}}',
                '{{WRAPPER}} .elemacy-nav__toggle:hover .elemacy-nav__toggle-label' => 'color: {{VALUE}}',
            ],
        ]);
        $this->add_control('toggle_background_hover', [
            'label'     => esc_html__('Background', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$toggle . ':hover' => 'background-color: {{VALUE}}'],
        ]);
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'      => 'toggle_label_typography',
            'selector'  => '{{WRAPPER}} .elemacy-nav__toggle-label',
            'separator' => 'before',
        ]);

        $this->add_responsive_control('toggle_icon_size', [
            'label'      => esc_html__('Icon Size', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'range'      => ['px' => ['min' => 10, 'max' => 80]],
            'selectors'  => [
                '{{WRAPPER}} .elemacy-nav__toggle-icon i'   => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .elemacy-nav__toggle-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('toggle_padding', [
            'label'      => esc_html__('Padding', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'selectors'  => [$toggle => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            'separator'  => 'before',
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'toggle_border',
            'selector' => $toggle,
        ]);

        $this->add_responsive_control('toggle_border_radius', [
            'label'      => esc_html__('Border Radius', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em', 'rem', 'custom'],
            'selectors'  => [$toggle => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'toggle_box_shadow',
            'selector' => $toggle,
        ]);

        $this->end_controls_section();
    }

    protected function register_toggle_close_style_controls(): void
    {
        $close = '{{WRAPPER}} .elemacy-nav__toggle-close';

        $this->start_controls_section('section_style_toggle_close', [
            'label'     => esc_html__('Close Button', 'elemacy'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['show_toggle' => 'yes'],
        ]);

        $this->start_controls_tabs('tabs_toggle_close_colors');

        $this->start_controls_tab('tab_toggle_close_normal', ['label' => esc_html__('Normal', 'elemacy')]);
        $this->add_control('toggle_close_color', [
            'label'     => esc_html__('Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .elemacy-nav__toggle-close-icon' => 'color: {{VALUE}}'],
        ]);
        $this->add_control('toggle_close_background', [
            'label'     => esc_html__('Background', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$close => 'background-color: {{VALUE}}'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('tab_toggle_close_hover', ['label' => esc_html__('Hover', 'elemacy')]);
        $this->add_control('toggle_close_color_hover', [
            'label'     => esc_html__('Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$close . ':hover .elemacy-nav__toggle-close-icon' => 'color: {{VALUE}}'],
        ]);
        $this->add_control('toggle_close_background_hover', [
            'label'     => esc_html__('Background', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$close . ':hover' => 'background-color: {{VALUE}}'],
        ]);
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control('toggle_close_icon_size', [
            'label'      => esc_html__('Icon Size', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'range'      => ['px' => ['min' => 10, 'max' => 80]],
            'selectors'  => [
                '{{WRAPPER}} .elemacy-nav__toggle-close-icon i'   => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .elemacy-nav__toggle-close-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
            'separator'  => 'before',
        ]);

        $this->add_responsive_control('toggle_close_padding', [
            'label'      => esc_html__('Padding', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'selectors'  => [$close => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'toggle_close_border',
            'selector' => $close,
        ]);

        $this->add_responsive_control('toggle_close_border_radius', [
            'label'      => esc_html__('Border Radius', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em', 'rem', 'custom'],
            'selectors'  => [$close => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'toggle_close_box_shadow',
            'selector' => $close,
        ]);

        $this->add_control('toggle_close_position_heading', [
            'label'     => esc_html__('Position', 'elemacy'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_responsive_control('toggle_close_top', [
            'label'      => esc_html__('Top', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%', 'custom'],
            'range'      => ['px' => ['min' => -500, 'max' => 500]],
            'default'    => ['unit' => 'px', 'size' => 10],
            'selectors'  => ['{{WRAPPER}} .elemacy-nav' => '--elemacy-close-top: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('toggle_close_right', [
            'label'      => esc_html__('Right', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%', 'custom'],
            'range'      => ['px' => ['min' => -500, 'max' => 500]],
            'default'    => ['unit' => 'px', 'size' => 10],
            'selectors'  => ['{{WRAPPER}} .elemacy-nav' => '--elemacy-close-right: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();
    }

    // =========================================================================
    // Render
    // =========================================================================

    protected function render(): void
    {
        $menus = $this->get_available_menus();
        if (empty($menus)) {
            return;
        }

        $settings = $this->get_settings_for_display();
        if (empty($settings['menu'])) {
            return;
        }

        $menu_id = 'elemacy-nav-menu-' . $this->get_id();

        $menu_html = wp_nav_menu([
            'echo'        => false,
            'menu'        => $settings['menu'],
            'menu_class'  => 'elemacy-nav__list',
            'container'   => '',
            'fallback_cb' => '__return_empty_string',
            'walker'      => new NavMenuWalker(),
        ]);

        if (empty($menu_html)) {
            return;
        }

        $breakpoint   = $settings['mobile_breakpoint'] ?: 'tablet';
        $overlay_type = $settings['overlay_type'] ?: 'fullscreen';
        $pointer      = $settings['pointer'] ?: 'none';

        $classes = [
            'elemacy-nav',
            'elemacy-nav--layout-' . esc_attr($settings['layout']),
            'elemacy-nav--breakpoint-' . esc_attr($breakpoint),
            'elemacy-nav--overlay-' . esc_attr($overlay_type),
        ];

        if ('none' !== $pointer) {
            $classes[] = 'elemacy-nav--pointer-' . esc_attr($pointer);
            if (in_array($pointer, ['underline', 'overline'], true)) {
                $classes[] = 'elemacy-nav--pointer-anim-' . esc_attr($settings['pointer_animation'] ?: 'slide');
            }
        }

        $this->add_render_attribute('wrapper', 'class', $classes);
        $this->add_render_attribute('nav', 'class', 'elemacy-nav__menu');
        $this->add_render_attribute('nav', 'id', $menu_id);

        $show_toggle = 'yes' === ($settings['show_toggle'] ?? '');
        $show_label  = $show_toggle && 'yes' === ($settings['show_toggle_label'] ?? '') && !empty($settings['toggle_label']);
?>
        <div <?php $this->print_render_attribute_string('wrapper'); ?>>
            <?php if ($show_toggle): ?>
                <div class="elemacy-nav__backdrop" aria-hidden="true"></div>
            <?php endif; ?>
            <div class="elemacy-nav__inner">
                <?php if ($show_toggle): ?>
                    <button class="elemacy-nav__toggle" type="button"
                        aria-expanded="false"
                        aria-controls="<?php echo esc_attr($menu_id); ?>"
                        aria-label="<?php esc_attr_e('Open navigation menu', 'elemacy'); ?>">
                        <span class="elemacy-nav__toggle-icon" aria-hidden="true">
                            <?php Icons_Manager::render_icon($settings['toggle_open_icon'], ['aria-hidden' => 'true']); ?>
                        </span>
                        <?php if ($show_label): ?>
                            <span class="elemacy-nav__toggle-label">
                                <?php echo esc_html($settings['toggle_label']); ?>
                            </span>
                        <?php endif; ?>
                    </button>
                <?php endif; ?>

                <nav <?php $this->print_render_attribute_string('nav'); ?>>
                    <?php if ($show_toggle): ?>
                        <button class="elemacy-nav__toggle-close" type="button"
                            aria-expanded="false"
                            aria-controls="<?php echo esc_attr($menu_id); ?>"
                            aria-label="<?php esc_attr_e('Close navigation menu', 'elemacy'); ?>">
                            <span class="elemacy-nav__toggle-close-icon" aria-hidden="true">
                                <?php Icons_Manager::render_icon($settings['toggle_close_icon'], ['aria-hidden' => 'true']); ?>
                            </span>
                        </button>
                    <?php endif; ?>
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
