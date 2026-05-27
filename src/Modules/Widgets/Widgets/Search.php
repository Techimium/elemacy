<?php

namespace Elemacy\Modules\Widgets\Widgets;

defined('ABSPATH') || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;

class Search extends BaseWidget
{
    private const SKIN_CLASSIC     = 'classic';
    private const SKIN_MINIMAL     = 'minimal';
    private const SKIN_FULL_SCREEN = 'full_screen';

    public function get_name(): string
    {
        return 'elemacy-search';
    }

    public function get_title(): string
    {
        return esc_html__('Search', 'elemacy');
    }

    public function get_icon(): string
    {
        return 'eicon-site-search';
    }

    public function get_keywords(): array
    {
        return ['search', 'form', 'find', 'magnifier', 'lookup'];
    }

    public function get_style_depends(): array
    {
        return ['elemacy-search'];
    }

    public function get_script_depends(): array
    {
        return ['elemacy-search'];
    }

    protected function register_controls(): void
    {
        $this->register_search_form_section();

        $this->register_input_style_section();
        $this->register_button_style_section();
        $this->register_icon_style_section();
        $this->register_toggle_style_section();
        $this->register_lightbox_style_section();
    }

    // ─── Content Controls ────────────────────────────────────────────────────

    private function register_search_form_section(): void
    {
        $this->start_controls_section('section_search_form', [
            'label' => esc_html__('Search Form', 'elemacy'),
        ]);

        $this->add_control('skin', [
            'label'        => esc_html__('Skin', 'elemacy'),
            'type'         => Controls_Manager::SELECT,
            'default'      => self::SKIN_CLASSIC,
            'options'      => [
                self::SKIN_CLASSIC     => esc_html__('Classic', 'elemacy'),
                self::SKIN_MINIMAL     => esc_html__('Minimal', 'elemacy'),
                self::SKIN_FULL_SCREEN => esc_html__('Full Screen', 'elemacy'),
            ],
            'prefix_class' => 'elemacy-search-form--skin-',
            'render_type'  => 'template',
        ]);

        $this->add_control('placeholder', [
            'label'     => esc_html__('Placeholder', 'elemacy'),
            'type'      => Controls_Manager::TEXT,
            'default'   => esc_html__('Search…', 'elemacy'),
            'dynamic'   => ['active' => true],
            'condition' => ['skin!' => self::SKIN_FULL_SCREEN],
        ]);

        $this->add_control('full_screen_placeholder', [
            'label'     => esc_html__('Placeholder', 'elemacy'),
            'type'      => Controls_Manager::TEXT,
            'default'   => esc_html__('Type to search…', 'elemacy'),
            'dynamic'   => ['active' => true],
            'condition' => ['skin' => self::SKIN_FULL_SCREEN],
        ]);

        $this->add_control('post_type', [
            'label'       => esc_html__('Scope by Post Type', 'elemacy'),
            'type'        => Controls_Manager::SELECT2,
            'multiple'    => true,
            'label_block' => true,
            'options'     => $this->get_public_post_type_options(),
            'default'     => [],
            'description' => esc_html__('Leave empty to use the default WordPress search across all post types.', 'elemacy'),
        ]);

        $this->add_responsive_control('size', [
            'label'        => esc_html__('Size', 'elemacy'),
            'type'         => Controls_Manager::SELECT,
            'default'      => 'md',
            'options'      => [
                'xs' => esc_html__('Extra Small', 'elemacy'),
                'sm' => esc_html__('Small', 'elemacy'),
                'md' => esc_html__('Medium', 'elemacy'),
                'lg' => esc_html__('Large', 'elemacy'),
                'xl' => esc_html__('Extra Large', 'elemacy'),
            ],
            'prefix_class' => 'elemacy-search-form--size%s-',
        ]);

        $this->add_control('button_type', [
            'label'        => esc_html__('Button Type', 'elemacy'),
            'type'         => Controls_Manager::SELECT,
            'default'      => 'icon',
            'options'      => [
                'icon' => esc_html__('Icon', 'elemacy'),
                'text' => esc_html__('Text', 'elemacy'),
            ],
            'condition'    => ['skin' => self::SKIN_CLASSIC],
            'prefix_class' => 'elemacy-search-form--button-type-',
            'render_type'  => 'template',
        ]);

        $this->add_control('button_text', [
            'label'   => esc_html__('Button Text', 'elemacy'),
            'type'    => Controls_Manager::TEXT,
            'default' => esc_html__('Search', 'elemacy'),
            'dynamic' => ['active' => true],
            'condition' => [
                'skin'        => self::SKIN_CLASSIC,
                'button_type' => 'text',
            ],
        ]);

        $this->add_control('icon', [
            'label'       => esc_html__('Icon', 'elemacy'),
            'type'        => Controls_Manager::ICONS,
            'default'     => [
                'value'   => 'eicon-search',
                'library' => 'eicons',
            ],
            // Hidden only when skin=classic + button_type=text. For minimal /
            // full_screen, `button_type` keeps its default ('icon'), so this
            // single AND-condition correctly shows the icon picker.
            'condition'   => ['button_type' => 'icon'],
            'render_type' => 'template',
        ]);

        $this->add_responsive_control('toggle_align', [
            'label'                => esc_html__('Toggle Alignment', 'elemacy'),
            'type'                 => Controls_Manager::CHOOSE,
            'options'              => [
                'start'  => [
                    'title' => esc_html__('Start', 'elemacy'),
                    'icon'  => 'eicon-text-align-left',
                ],
                'center' => [
                    'title' => esc_html__('Center', 'elemacy'),
                    'icon'  => 'eicon-text-align-center',
                ],
                'end'    => [
                    'title' => esc_html__('End', 'elemacy'),
                    'icon'  => 'eicon-text-align-right',
                ],
            ],
            'classes'              => 'elementor-control-start-end',
            'selectors_dictionary' => [
                'left'  => is_rtl() ? 'end' : 'start',
                'right' => is_rtl() ? 'start' : 'end',
            ],
            'default'              => 'start',
            'condition'            => ['skin' => self::SKIN_FULL_SCREEN],
            'selectors'            => [
                '{{WRAPPER}}' => 'text-align: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    // ─── Style Controls ──────────────────────────────────────────────────────

    private function register_input_style_section(): void
    {
        $this->start_controls_section('section_style_input', [
            'label'     => esc_html__('Input', 'elemacy'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['skin!' => self::SKIN_FULL_SCREEN],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'input_typography',
            'selector' => '{{WRAPPER}} .elemacy-search-form__input',
            'global'   => ['default' => Global_Typography::TYPOGRAPHY_TEXT],
        ]);

        $this->start_controls_tabs('tabs_input_style');

        $this->start_controls_tab('tab_input_normal', [
            'label' => esc_html__('Normal', 'elemacy'),
        ]);

        $this->add_control('input_text_color', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .elemacy-search-form__input' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('input_placeholder_color', [
            'label'     => esc_html__('Placeholder Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .elemacy-search-form__input::placeholder' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('input_background_color', [
            'label'     => esc_html__('Background Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .elemacy-search-form__input' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'      => 'input_border',
            'selector'  => '{{WRAPPER}} .elemacy-search-form__input',
            'separator' => 'before',
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('tab_input_focus', [
            'label' => esc_html__('Focus', 'elemacy'),
        ]);

        $this->add_control('input_text_color_focus', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .elemacy-search-form__input:focus' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('input_background_color_focus', [
            'label'     => esc_html__('Background Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .elemacy-search-form__input:focus' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('input_border_color_focus', [
            'label'     => esc_html__('Border Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .elemacy-search-form__input:focus' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control('input_border_radius', [
            'label'      => esc_html__('Border Radius', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em', 'rem', 'custom'],
            'separator'  => 'before',
            'selectors'  => [
                '{{WRAPPER}} .elemacy-search-form__input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('input_padding', [
            'label'      => esc_html__('Padding', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', 'rem', '%', 'custom'],
            'selectors'  => [
                '{{WRAPPER}} .elemacy-search-form__input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    private function register_button_style_section(): void
    {
        $this->start_controls_section('section_style_button', [
            'label'     => esc_html__('Button', 'elemacy'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['skin' => self::SKIN_CLASSIC],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'      => 'button_typography',
            'selector'  => '{{WRAPPER}} .elemacy-search-form__submit',
            'global'    => ['default' => Global_Typography::TYPOGRAPHY_TEXT],
            'condition' => ['button_type' => 'text'],
        ]);

        $this->add_responsive_control('button_icon_size', [
            'label'      => esc_html__('Icon Size', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'range'      => [
                'px' => [
                    'min' => 8,
                    'max' => 64,
                ],
                'em' => [
                    'min'  => 0.5,
                    'max'  => 5,
                    'step' => 0.1,
                ],
            ],
            'condition'  => ['button_type' => 'icon'],
            'selectors'  => [
                '{{WRAPPER}} .elemacy-search-form__submit .elemacy-search-form__icon' => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .elemacy-search-form__submit svg'                        => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->start_controls_tabs('tabs_button_style');

        $this->start_controls_tab('tab_button_normal', [
            'label' => esc_html__('Normal', 'elemacy'),
        ]);

        $this->add_control('button_text_color', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .elemacy-search-form__submit'     => 'color: {{VALUE}};',
                '{{WRAPPER}} .elemacy-search-form__submit svg' => 'fill: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_background_color', [
            'label'     => esc_html__('Background Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'global'    => ['default' => Global_Colors::COLOR_ACCENT],
            'selectors' => [
                '{{WRAPPER}} .elemacy-search-form__submit' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('tab_button_hover', [
            'label' => esc_html__('Hover', 'elemacy'),
        ]);

        $this->add_control('button_text_color_hover', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .elemacy-search-form__submit:hover'     => 'color: {{VALUE}};',
                '{{WRAPPER}} .elemacy-search-form__submit:hover svg' => 'fill: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_background_color_hover', [
            'label'     => esc_html__('Background Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .elemacy-search-form__submit:hover' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_border_color_hover', [
            'label'     => esc_html__('Border Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .elemacy-search-form__submit:hover' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'      => 'button_border',
            'selector'  => '{{WRAPPER}} .elemacy-search-form__submit',
            'separator' => 'before',
        ]);

        $this->add_responsive_control('button_border_radius', [
            'label'      => esc_html__('Border Radius', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em', 'rem', 'custom'],
            'selectors'  => [
                '{{WRAPPER}} .elemacy-search-form__submit' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'button_box_shadow',
            'selector' => '{{WRAPPER}} .elemacy-search-form__submit',
        ]);

        $this->add_responsive_control('button_padding', [
            'label'      => esc_html__('Padding', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', 'rem', '%', 'custom'],
            'condition'  => ['button_type' => 'text'],
            'selectors'  => [
                '{{WRAPPER}} .elemacy-search-form__submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    private function register_icon_style_section(): void
    {
        $this->start_controls_section('section_style_icon', [
            'label'     => esc_html__('Icon', 'elemacy'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['skin' => self::SKIN_MINIMAL],
        ]);

        $this->add_responsive_control('minimal_icon_size', [
            'label'      => esc_html__('Icon Size', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'range'      => [
                'px' => [
                    'min' => 8,
                    'max' => 64,
                ],
                'em' => [
                    'min'  => 0.5,
                    'max'  => 5,
                    'step' => 0.1,
                ],
            ],
            'selectors'  => [
                '{{WRAPPER}} .elemacy-search-form--skin-minimal .elemacy-search-form__icon'     => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .elemacy-search-form--skin-minimal .elemacy-search-form__icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('minimal_icon_spacing', [
            'label'      => esc_html__('Spacing', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'range'      => [
                'px' => [
                    'min' => 0,
                    'max' => 60,
                ],
            ],
            'selectors'  => [
                '{{WRAPPER}} .elemacy-search-form--skin-minimal .elemacy-search-form__input' => 'padding-inline-start: calc({{SIZE}}{{UNIT}} * 2 + 1em);',
                '{{WRAPPER}} .elemacy-search-form--skin-minimal .elemacy-search-form__icon'  => 'inset-inline-start: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->start_controls_tabs('tabs_minimal_icon_style');

        $this->start_controls_tab('tab_minimal_icon_normal', [
            'label' => esc_html__('Normal', 'elemacy'),
        ]);

        $this->add_control('minimal_icon_color', [
            'label'     => esc_html__('Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .elemacy-search-form--skin-minimal .elemacy-search-form__icon'     => 'color: {{VALUE}};',
                '{{WRAPPER}} .elemacy-search-form--skin-minimal .elemacy-search-form__icon svg' => 'fill: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('tab_minimal_icon_focus', [
            'label' => esc_html__('Focus', 'elemacy'),
        ]);

        $this->add_control('minimal_icon_color_focus', [
            'label'     => esc_html__('Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .elemacy-search-form--skin-minimal.elemacy-search-form--focus .elemacy-search-form__icon'     => 'color: {{VALUE}};',
                '{{WRAPPER}} .elemacy-search-form--skin-minimal.elemacy-search-form--focus .elemacy-search-form__icon svg' => 'fill: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    private function register_toggle_style_section(): void
    {
        $this->start_controls_section('section_style_toggle', [
            'label'     => esc_html__('Toggle', 'elemacy'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['skin' => self::SKIN_FULL_SCREEN],
        ]);

        $this->add_responsive_control('toggle_size', [
            'label'      => esc_html__('Size', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'range'      => [
                'px' => [
                    'min' => 16,
                    'max' => 96,
                ],
                'em' => [
                    'min'  => 0.5,
                    'max'  => 6,
                    'step' => 0.1,
                ],
            ],
            'selectors'  => [
                '{{WRAPPER}} .elemacy-search-form__toggle'     => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .elemacy-search-form__toggle svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->start_controls_tabs('tabs_toggle_style');

        $this->start_controls_tab('tab_toggle_normal', [
            'label' => esc_html__('Normal', 'elemacy'),
        ]);

        $this->add_control('toggle_color', [
            'label'     => esc_html__('Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .elemacy-search-form__toggle'     => 'color: {{VALUE}};',
                '{{WRAPPER}} .elemacy-search-form__toggle svg' => 'fill: {{VALUE}};',
            ],
        ]);

        $this->add_control('toggle_background_color', [
            'label'     => esc_html__('Background Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .elemacy-search-form__toggle' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('tab_toggle_hover', [
            'label' => esc_html__('Hover', 'elemacy'),
        ]);

        $this->add_control('toggle_color_hover', [
            'label'     => esc_html__('Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .elemacy-search-form__toggle:hover'     => 'color: {{VALUE}};',
                '{{WRAPPER}} .elemacy-search-form__toggle:hover svg' => 'fill: {{VALUE}};',
            ],
        ]);

        $this->add_control('toggle_background_color_hover', [
            'label'     => esc_html__('Background Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .elemacy-search-form__toggle:hover' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'      => 'toggle_border',
            'selector'  => '{{WRAPPER}} .elemacy-search-form__toggle',
            'separator' => 'before',
        ]);

        $this->add_responsive_control('toggle_border_radius', [
            'label'      => esc_html__('Border Radius', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em', 'rem', 'custom'],
            'selectors'  => [
                '{{WRAPPER}} .elemacy-search-form__toggle' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('toggle_padding', [
            'label'      => esc_html__('Padding', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', 'rem', '%', 'custom'],
            'selectors'  => [
                '{{WRAPPER}} .elemacy-search-form__toggle' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    private function register_lightbox_style_section(): void
    {
        $this->start_controls_section('section_style_lightbox', [
            'label'     => esc_html__('Lightbox', 'elemacy'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['skin' => self::SKIN_FULL_SCREEN],
        ]);

        $this->add_control('lightbox_background_color', [
            'label'     => esc_html__('Background Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'default'   => 'rgba(0, 0, 0, 0.8)',
            'selectors' => [
                '{{WRAPPER}} .elemacy-search-form__container' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('lightbox_input_color', [
            'label'     => esc_html__('Input Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#FFFFFF',
            'selectors' => [
                '{{WRAPPER}} .elemacy-search-form--skin-full_screen .elemacy-search-form__input'              => 'color: {{VALUE}};',
                '{{WRAPPER}} .elemacy-search-form--skin-full_screen .elemacy-search-form__input::placeholder' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'lightbox_input_typography',
            'selector' => '{{WRAPPER}} .elemacy-search-form--skin-full_screen .elemacy-search-form__input',
            'global'   => ['default' => Global_Typography::TYPOGRAPHY_PRIMARY],
        ]);

        $this->add_control('lightbox_close_color', [
            'label'     => esc_html__('Close Button Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#FFFFFF',
            'selectors' => [
                '{{WRAPPER}} .elemacy-search-form__close'     => 'color: {{VALUE}};',
                '{{WRAPPER}} .elemacy-search-form__close svg' => 'fill: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('lightbox_close_size', [
            'label'      => esc_html__('Close Button Size', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'range'      => [
                'px' => [
                    'min' => 16,
                    'max' => 96,
                ],
                'em' => [
                    'min'  => 0.5,
                    'max'  => 6,
                    'step' => 0.1,
                ],
            ],
            'selectors'  => [
                '{{WRAPPER}} .elemacy-search-form__close'     => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .elemacy-search-form__close svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    // ─── Rendering ───────────────────────────────────────────────────────────

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $skin     = $this->resolve_skin($settings);
        $input_id = 'elemacy-search-form-' . $this->get_id();

        $this->add_render_attribute('form', [
            'class'  => 'elemacy-search-form',
            'role'   => 'search',
            'method' => 'get',
            'action' => esc_url(home_url('/')),
        ]);

        $this->add_render_attribute('container', [
            'class' => 'elemacy-search-form__container',
        ]);

        $this->add_render_attribute('label', [
            'class' => 'elemacy-search-form__label',
            'for'   => $input_id,
        ]);

        $this->add_render_attribute('input', [
            'id'          => $input_id,
            'class'       => 'elemacy-search-form__input',
            'type'        => 'search',
            'name'        => 's',
            'autocomplete' => 'off',
            'placeholder' => $this->resolve_placeholder($settings, $skin),
            'value'       => get_search_query(),
        ]);

        ?>
        <form <?php echo $this->get_render_attribute_string('form'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's get_render_attribute_string() returns pre-sanitized HTML attributes ?>>
            <?php if (self::SKIN_FULL_SCREEN === $skin) : ?>
                <?php $this->render_toggle($settings); ?>
            <?php endif; ?>

            <div <?php echo $this->get_render_attribute_string('container'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's get_render_attribute_string() returns pre-sanitized HTML attributes ?>>
                <label <?php echo $this->get_render_attribute_string('label'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's get_render_attribute_string() returns pre-sanitized HTML attributes ?>>
                    <?php echo esc_html__('Search', 'elemacy'); ?>
                </label>

                <?php if (self::SKIN_MINIMAL === $skin) : ?>
                    <span class="elemacy-search-form__icon" aria-hidden="true">
                        <?php $this->render_icon($settings); ?>
                    </span>
                <?php endif; ?>

                <input <?php echo $this->get_render_attribute_string('input'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's get_render_attribute_string() returns pre-sanitized HTML attributes ?>>

                <?php if (self::SKIN_CLASSIC === $skin) : ?>
                    <?php $this->render_submit_button($settings); ?>
                <?php endif; ?>

                <?php if (self::SKIN_FULL_SCREEN === $skin) : ?>
                    <button type="button" class="elemacy-search-form__close" aria-label="<?php echo esc_attr__('Close search', 'elemacy'); ?>">
                        <?php
                        Icons_Manager::render_icon(
                            [
                                'library' => 'eicons',
                                'value'   => 'eicon-close',
                            ],
                            ['aria-hidden' => 'true']
                        );
                        ?>
                    </button>
                <?php endif; ?>
            </div>

            <?php $this->render_post_type_inputs($settings); ?>
        </form>
        <?php
    }

    // ─── Render Partials ─────────────────────────────────────────────────────

    private function render_submit_button(array $settings): void
    {
        $button_type = $settings['button_type'] ?? 'icon';
        ?>
        <button type="submit" class="elemacy-search-form__submit" aria-label="<?php echo esc_attr__('Search', 'elemacy'); ?>">
            <?php if ('icon' === $button_type) : ?>
                <span class="elemacy-search-form__icon" aria-hidden="true">
                    <?php $this->render_icon($settings); ?>
                </span>
            <?php else : ?>
                <span class="elemacy-search-form__submit-text">
                    <?php echo esc_html($settings['button_text'] ?? ''); ?>
                </span>
            <?php endif; ?>
        </button>
        <?php
    }

    private function render_toggle(array $settings): void
    {
        ?>
        <button type="button" class="elemacy-search-form__toggle" aria-label="<?php echo esc_attr__('Open search', 'elemacy'); ?>" aria-expanded="false">
            <span class="elemacy-search-form__icon" aria-hidden="true">
                <?php $this->render_icon($settings); ?>
            </span>
        </button>
        <?php
    }

    private function render_icon(array $settings): void
    {
        $icon = $settings['icon'] ?? [];

        if (empty($icon['value'])) {
            $icon = [
                'value'   => 'eicon-search',
                'library' => 'eicons',
            ];
        }

        Icons_Manager::render_icon($icon, ['aria-hidden' => 'true']);
    }

    private function render_post_type_inputs(array $settings): void
    {
        $post_types = $settings['post_type'] ?? [];

        if (empty($post_types) || !is_array($post_types)) {
            return;
        }

        $multi = count($post_types) > 1;

        foreach ($post_types as $post_type) {
            printf(
                '<input type="hidden" name="%s" value="%s">',
                esc_attr($multi ? 'post_type[]' : 'post_type'),
                esc_attr($post_type)
            );
        }
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    private function resolve_skin(array $settings): string
    {
        $skin = $settings['skin'] ?? self::SKIN_CLASSIC;

        return in_array($skin, [self::SKIN_CLASSIC, self::SKIN_MINIMAL, self::SKIN_FULL_SCREEN], true)
            ? $skin
            : self::SKIN_CLASSIC;
    }

    private function resolve_placeholder(array $settings, string $skin): string
    {
        if (self::SKIN_FULL_SCREEN === $skin) {
            return (string) ($settings['full_screen_placeholder'] ?? '');
        }

        return (string) ($settings['placeholder'] ?? '');
    }

    /**
     * @return array<string, string> slug => label
     */
    private function get_public_post_type_options(): array
    {
        $post_types = get_post_types(['public' => true], 'objects');
        $options    = [];

        foreach ($post_types as $slug => $object) {
            if ('attachment' === $slug) {
                continue;
            }

            $options[$slug] = !empty($object->label) ? $object->label : $slug;
        }

        return $options;
    }
}
