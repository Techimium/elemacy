<?php

namespace Elemacy\Modules\LoopBuilder\Widgets\Controls;

defined('ABSPATH') || exit;

use Elemacy\Widgets\BaseWidget;
use Elementor\Controls_Manager;

/**
 * Slider behaviour for every carousel widget. These setting names are also
 * what loop-carousel.js reads, so they stay the same on every carousel.
 *
 * @internal
 */
final class SliderSection
{
    public static function register_slide_controls(BaseWidget $widget): void
    {
        $widget->add_responsive_control(
            'slides_per_view',
            [
                'label' => esc_html__('Slides Per View', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => '3',
                'options' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                    '6' => '6',
                ],
                'frontend_available' => true,
            ]
        );

        $widget->add_responsive_control(
            'slides_to_scroll',
            [
                'label' => esc_html__('Slides to Scroll', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => '1',
                'options' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                    '6' => '6',
                ],
                'frontend_available' => true,
            ]
        );

        $widget->add_control(
            'equal_height',
            [
                'label' => esc_html__('Equal Height', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .swiper-slide' => 'height: auto;',
                    '{{WRAPPER}} .elemacy-loop-item' => 'height: 100%;',
                ],
            ]
        );
    }

    public static function register(BaseWidget $widget): void
    {
        $widget->start_controls_section(
            'section_carousel_settings',
            [
                'label' => esc_html__('Carousel Settings', 'elemacy'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $widget->add_control(
            'autoplay',
            [
                'label' => esc_html__('Autoplay', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'frontend_available' => true,
            ]
        );

        $widget->add_control(
            'autoplay_speed',
            [
                'label' => esc_html__('Autoplay Speed', 'elemacy'),
                'type' => Controls_Manager::NUMBER,
                'default' => 5000,
                'condition' => [
                    'autoplay' => 'yes',
                ],
                'frontend_available' => true,
            ]
        );

        $widget->add_control(
            'pause_on_hover',
            [
                'label' => esc_html__('Pause on Hover', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => [
                    'autoplay' => 'yes',
                ],
                'frontend_available' => true,
            ]
        );

        $widget->add_control(
            'loop',
            [
                'label' => esc_html__('Infinite Loop', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'frontend_available' => true,
            ]
        );

        $widget->add_control(
            'transition_speed',
            [
                'label' => esc_html__('Transition Speed (ms)', 'elemacy'),
                'type' => Controls_Manager::NUMBER,
                'default' => 500,
                'frontend_available' => true,
            ]
        );

        $widget->add_control(
            'navigation',
            [
                'label' => esc_html__('Navigation', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => 'both',
                'options' => [
                    'both' => esc_html__('Arrows and Pagination', 'elemacy'),
                    'arrows' => esc_html__('Arrows', 'elemacy'),
                    'pagination' => esc_html__('Pagination', 'elemacy'),
                    'none' => esc_html__('None', 'elemacy'),
                ],
                'frontend_available' => true,
            ]
        );

        $widget->end_controls_section();
    }
}
