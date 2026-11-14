<?php

namespace Elemacy\Modules\LoopBuilder\Widgets\Controls;

defined('ABSPATH') || exit;

use Elemacy\Loop\Pagination\PaginationType;
use Elemacy\Widgets\BaseWidget;
use Elementor\Controls_Manager;

/**
 * Content > Pagination: the setting names PaginationRenderer and the public
 * policy read, shared by every paginated list widget.
 *
 * @internal
 */
final class PaginationSection
{
    public static function register(BaseWidget $widget): void
    {
        $widget->start_controls_section(
            'section_pagination',
            [
                'label' => esc_html__('Pagination', 'elemacy'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $widget->add_control(
            'pagination_type',
            [
                'label' => esc_html__('Pagination', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => '',
                'options' => [
                    '' => esc_html__('None', 'elemacy'),
                    PaginationType::NUMBERS => esc_html__('Numbers', 'elemacy'),
                    PaginationType::PREV_NEXT => esc_html__('Previous/Next', 'elemacy'),
                    PaginationType::NUMBERS_AND_PREV_NEXT => esc_html__('Numbers + Previous/Next', 'elemacy'),
                    PaginationType::LOAD_MORE => esc_html__('Load More', 'elemacy'),
                    PaginationType::INFINITE_SCROLL => esc_html__('Infinite Scroll', 'elemacy'),
                ],
            ]
        );

        // Load More and Infinite Scroll always load in place, so only the link
        // types offer the switch.
        $widget->add_control(
            'pagination_ajax',
            [
                'label' => esc_html__('AJAX Pagination', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => 'no',
                'description' => esc_html__('Fetch the next page without reloading the entire page.', 'elemacy'),
                'condition' => [
                    'pagination_type' => PaginationType::LINKS,
                ],
            ]
        );

        $incremental = ['pagination_type' => PaginationType::INCREMENTAL];

        $widget->add_control(
            'load_more_text',
            [
                'label' => esc_html__('Button Text', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => esc_html__('Load More', 'elemacy'),
                'condition' => $incremental,
            ]
        );

        $widget->add_control(
            'load_more_loading_text',
            [
                'label' => esc_html__('Loading Text', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => esc_html__('Loading…', 'elemacy'),
                'condition' => $incremental,
            ]
        );

        $widget->add_control(
            'load_more_end_text',
            [
                'label' => esc_html__('End Message', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'description' => esc_html__('Shown after the last page has loaded. Leave empty to show nothing.', 'elemacy'),
                'condition' => $incremental,
            ]
        );

        $widget->add_control(
            'pagination_page_limit',
            [
                'label' => esc_html__('Page Limit', 'elemacy'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'default' => '',
                'description' => esc_html__('The most pages this list offers. Leave empty for no limit.', 'elemacy'),
                'condition' => [
                    'pagination_type!' => '',
                ],
            ]
        );

        $prev_next = ['pagination_type' => [PaginationType::PREV_NEXT, PaginationType::NUMBERS_AND_PREV_NEXT]];

        $widget->add_control(
            'pagination_prev_label',
            [
                'label' => esc_html__('Previous Label', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => esc_html__('« Previous', 'elemacy'),
                'condition' => $prev_next,
            ]
        );

        $widget->add_control(
            'pagination_next_label',
            [
                'label' => esc_html__('Next Label', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => esc_html__('Next »', 'elemacy'),
                'condition' => $prev_next,
            ]
        );

        $widget->end_controls_section();
    }
}
