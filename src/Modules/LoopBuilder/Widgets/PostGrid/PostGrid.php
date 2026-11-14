<?php

namespace Elemacy\Modules\LoopBuilder\Widgets\PostGrid;

use Elemacy\Loop\Contracts\LoopDataSourceInterface;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Modules\LoopBuilder\PostCard\PostCardDefinition;
use Elemacy\Modules\LoopBuilder\Widgets\Controls\PaginationSection;
use Elemacy\Modules\LoopBuilder\Widgets\Controls\PaginationStyleSection;
use Elemacy\Modules\LoopBuilder\Widgets\PostWidget;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Posts as a grid, list or masonry of cards, with no template to build.
 *
 * @internal
 */
class PostGrid extends PostWidget
{
    private const LAYOUTS = [PostCardDefinition::LAYOUT_GRID, 'list', 'masonry'];

    public function get_name()
    {
        return 'elemacy-post-grid';
    }

    public function get_title()
    {
        return esc_html__('Post Grid', 'elemacy');
    }

    public function get_icon()
    {
        return 'eicon-posts-grid';
    }

    public function get_keywords()
    {
        return ['posts', 'grid', 'blog', 'list', 'masonry', 'custom post type', 'cpt', 'archive'];
    }

    public function get_style_depends()
    {
        return ['elemacy-loop-grid', 'elemacy-post-card'];
    }

    public function get_script_depends()
    {
        return ['elemacy-loop-grid', 'elemacy-loop-incremental', 'elemacy-post-grid'];
    }

    protected function register_controls()
    {
        $this->register_layout_section();
        $this->register_query_section();
        $this->register_card_content_section();
        PaginationSection::register($this);
        $this->register_card_style_sections();
        PaginationStyleSection::register($this);
    }

    private function register_layout_section(): void
    {
        $this->start_controls_section(
            'section_layout',
            [
                'label' => esc_html__('Layout', 'elemacy'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            PostCardDefinition::LAYOUT,
            [
                'label' => esc_html__('Layout', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => PostCardDefinition::LAYOUT_GRID,
                'options' => [
                    PostCardDefinition::LAYOUT_GRID => esc_html__('Grid', 'elemacy'),
                    'list' => esc_html__('List', 'elemacy'),
                    'masonry' => esc_html__('Masonry', 'elemacy'),
                ],
            ]
        );

        $this->add_responsive_control(
            'columns',
            [
                'label' => esc_html__('Columns', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => '3',
                'tablet_default' => '2',
                'mobile_default' => '1',
                'options' => array_combine(['1', '2', '3', '4', '5', '6'], ['1', '2', '3', '4', '5', '6']),
                'selectors' => ['{{WRAPPER}} .elemacy-loop-grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));'],
                'condition' => [PostCardDefinition::LAYOUT . '!' => 'list'],
            ]
        );

        $this->register_layout_page_size();

        foreach (
            [
                'column_gap' => [esc_html__('Column Gap', 'elemacy'), 'column-gap'],
                'row_gap' => [esc_html__('Row Gap', 'elemacy'), 'row-gap'],
            ] as $id => [$label, $property]
        ) {
            $this->add_responsive_control(
                $id,
                [
                    'label' => $label,
                    'type' => Controls_Manager::SLIDER,
                    'size_units' => ['px', 'em'],
                    'range' => [
                        'px' => [
                            'min' => 0,
                            'max' => 100,
                        ],
                    ],
                    'selectors' => ['{{WRAPPER}} .elemacy-loop-grid' => $property . ': {{SIZE}}{{UNIT}};'],
                ]
            );
        }

        $this->end_controls_section();
    }

    protected function render_list(array $settings, LoopDataSourceInterface $source, LoopResultDTO $result, int $page): void
    {
        $layout = (string) ($settings[PostCardDefinition::LAYOUT] ?? PostCardDefinition::LAYOUT_GRID);

        if (!in_array($layout, self::LAYOUTS, true)) {
            $layout = PostCardDefinition::LAYOUT_GRID;
        }

        $this->render_paginated_list($settings, $source, $result, $page, 'elemacy-post-grid elemacy-post-grid--' . $layout);
    }
}
