<?php

namespace Elemacy\Modules\LoopBuilder\Widgets\Controls;

defined('ABSPATH') || exit;

use Elemacy\Widgets\BaseWidget;
use Elementor\Controls_Manager;

/**
 * Style tab sections for every carousel widget's slides, arrows and dots.
 *
 * @internal
 */
final class SliderStyleSection
{
    public static function register(BaseWidget $widget): void
    {
        self::register_carousel_style($widget);
        // Since pagination in carousel is handled by swiper, we add its styles.
        self::register_navigation_style($widget);
        self::register_dots_style($widget);
    }

    private static function register_carousel_style(BaseWidget $widget): void
    {
        $widget->start_controls_section(
            'section_carousel_style',
            [
                'label' => esc_html__('Carousel', 'elemacy'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $widget->add_responsive_control(
            'space_between',
            [
                'label' => esc_html__('Space Between', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'size' => 20,
                ],
                'frontend_available' => true,
            ]
        );

        $widget->end_controls_section();
    }

    private static function register_navigation_style(BaseWidget $widget): void
    {
        $widget->start_controls_section(
            'section_navigation_style',
            [
                'label' => esc_html__('Navigation Arrows', 'elemacy'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'navigation' => ['both', 'arrows'],
                ],
            ]
        );

        $widget->add_control(
            'arrows_size',
            [
                'label' => esc_html__('Size', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-swiper-button' => 'font-size: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $widget->add_control(
            'arrows_color',
            [
                'label' => esc_html__('Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-swiper-button' => 'color: {{VALUE}};',
                ],
            ]
        );

        $widget->add_control(
            'arrows_bg_color',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-swiper-button' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $widget->add_control(
            'arrows_border_radius',
            [
                'label' => esc_html__('Border Radius', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-swiper-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $widget->add_control(
            'arrows_padding',
            [
                'label' => esc_html__('Padding', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-swiper-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $widget->end_controls_section();
    }

    private static function register_dots_style(BaseWidget $widget): void
    {
        $widget->start_controls_section(
            'section_pagination_style',
            [
                'label' => esc_html__('Pagination', 'elemacy'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'navigation' => ['both', 'pagination'],
                ],
            ]
        );

        $widget->add_control(
            'pagination_size',
            [
                'label' => esc_html__('Size', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 5,
                        'max' => 30,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .swiper-pagination-bullet' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $widget->add_responsive_control(
            'pagination_alignment',
            [
                'label' => esc_html__('Alignment', 'elemacy'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => esc_html__('Left', 'elemacy'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => esc_html__('Center', 'elemacy'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => esc_html__('Right', 'elemacy'),
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => 'center',
                'selectors' => [
                    '{{WRAPPER}} .swiper-pagination' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $widget->add_control(
            'pagination_offset',
            [
                'label' => esc_html__('Offset', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 200,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-loop-carousel-container .elemacy-loop-carousel' => 'padding-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $widget->add_control(
            'pagination_color',
            [
                'label' => esc_html__('Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .swiper-pagination-bullet' => 'background: {{VALUE}};',
                ],
            ]
        );

        $widget->add_control(
            'pagination_active_color',
            [
                'label' => esc_html__('Active Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .swiper-pagination-bullet-active' => 'background: {{VALUE}};',
                ],
            ]
        );

        $widget->end_controls_section();
    }
}
