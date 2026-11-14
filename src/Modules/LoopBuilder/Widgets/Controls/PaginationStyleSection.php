<?php

namespace Elemacy\Modules\LoopBuilder\Widgets\Controls;

defined('ABSPATH') || exit;

use Elemacy\Widgets\BaseWidget;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

/**
 * Style > Pagination for every paginated list widget.
 *
 * @internal
 */
final class PaginationStyleSection
{
    public static function register(BaseWidget $widget): void
    {
        $widget->start_controls_section(
            'section_pagination_style',
            [
                'label' => esc_html__('Pagination', 'elemacy'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $widget->add_responsive_control(
            'pagination_alignment',
            [
                'label' => esc_html__('Alignment', 'elemacy'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'flex-start' => [
                        'title' => esc_html__('Left', 'elemacy'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => esc_html__('Center', 'elemacy'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'flex-end' => [
                        'title' => esc_html__('Right', 'elemacy'),
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => 'center',
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination' => 'display: flex; flex-wrap: wrap; justify-content: {{VALUE}};',
                ],
            ]
        );

        $widget->add_responsive_control(
            'pagination_spacing',
            [
                'label' => esc_html__('Top Spacing', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination' => 'margin-top: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $widget->add_responsive_control(
            'pagination_gap',
            [
                'label' => esc_html__('Items Gap', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $widget->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'pagination_typography',
                'selector' => '{{WRAPPER}} .elemacy-pagination .page-numbers',
            ]
        );

        $widget->start_controls_tabs('pagination_style_tabs');

        // Normal Tab.
        $widget->start_controls_tab('pagination_style_normal', ['label' => esc_html__('Normal', 'elemacy')]);

        $widget->add_control(
            'pagination_color',
            [
                'label' => esc_html__('Text Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers' => 'color: {{VALUE}};',
                ],
            ]
        );

        $widget->add_control(
            'pagination_bg_color',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $widget->add_control(
            'pagination_border_color',
            [
                'label' => esc_html__('Border Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $widget->end_controls_tab();

        // Hover Tab.
        $widget->start_controls_tab('pagination_style_hover', ['label' => esc_html__('Hover', 'elemacy')]);

        $widget->add_control(
            'pagination_color_hover',
            [
                'label' => esc_html__('Text Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $widget->add_control(
            'pagination_bg_color_hover',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $widget->add_control(
            'pagination_border_color_hover',
            [
                'label' => esc_html__('Border Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers:hover' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $widget->end_controls_tab();

        // Active Tab.
        $widget->start_controls_tab('pagination_style_active', ['label' => esc_html__('Active', 'elemacy')]);

        $widget->add_control(
            'pagination_color_active',
            [
                'label' => esc_html__('Text Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers.current' => 'color: {{VALUE}};',
                ],
            ]
        );

        $widget->add_control(
            'pagination_bg_color_active',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers.current' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $widget->add_control(
            'pagination_border_color_active',
            [
                'label' => esc_html__('Border Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers.current' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $widget->end_controls_tab();
        $widget->end_controls_tabs();

        $widget->add_control(
            'pagination_border_width',
            [
                'label' => esc_html__('Border Width', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; border-style: solid;',
                ],
                'separator' => 'before',
            ]
        );

        $widget->add_control(
            'pagination_border_radius',
            [
                'label' => esc_html__('Border Radius', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $widget->add_responsive_control(
            'pagination_padding',
            [
                'label' => esc_html__('Padding', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; display: inline-flex; justify-content: center; align-items: center; text-decoration: none; transition: all 0.3s;',
                ],
            ]
        );

        $widget->end_controls_section();
    }
}
