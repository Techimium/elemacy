<?php

namespace Elemacy\Modules\LoopBuilder\Widgets\LoopGrid;

use Elemacy\Core\Container;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Contracts\ItemPresenterInterface;
use Elemacy\Loop\Rendering\TemplateItemPresenter;
use Elementor\Controls_Manager;
use Elementor\Plugin;
use Elemacy\Modules\LoopBuilder\Widgets\Controls\PaginationSection;
use Elemacy\Modules\LoopBuilder\Widgets\Controls\PaginationStyleSection;
use Elemacy\Modules\LoopBuilder\Widgets\LoopWidget;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * @internal
 */
class LoopGrid extends LoopWidget
{
    public function get_name()
    {
        return 'elemacy-loop-grid';
    }

    public function get_title()
    {
        return esc_html__('Loop Builder', 'elemacy');
    }

    public function get_icon()
    {
        return 'eicon-loop-builder';
    }

    public function get_keywords()
    {
        return ['loop', 'builder', 'grid', 'post', 'custom post type', 'dynamic'];
    }

    public function get_style_depends()
    {
        return ['elemacy-loop-grid'];
    }

    public function get_script_depends()
    {
        return ['elemacy-loop-grid', 'elemacy-loop-incremental'];
    }

    protected function register_controls()
    {
        $this->register_layout_section();
        $this->register_query_section();
        PaginationSection::register($this);
        PaginationStyleSection::register($this);
    }

    protected function register_layout_section()
    {
        $this->start_controls_section(
            'section_layout',
            [
                'label' => esc_html__('Layout', 'elemacy'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'template_id',
            [
                'label' => esc_html__('Choose Template', 'elemacy'),
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'options' => $this->get_elementor_templates(),
                'default' => '',
                'description' => esc_html__('Select a loop template from the Template Library.', 'elemacy'),
            ]
        );

        if (empty($this->get_loop_templates())) {
            $this->add_control(
                'no_templates_notice',
                [
                    'type' => Controls_Manager::RAW_HTML,
                    'raw' => sprintf(
                        '<strong>%s</strong><br>%s <a href="%s" target="_blank" rel="noopener">%s</a>',
                        esc_html__('No loop templates yet', 'elemacy'),
                        esc_html__('Create a loop template in the Template Library, then select it here.', 'elemacy'),
                        esc_url(admin_url('admin.php?page=elemacy#/library')),
                        esc_html__('Open Template Library →', 'elemacy')
                    ),
                    'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
                ]
            );
        }

        $this->add_responsive_control(
            'columns',
            [
                'label' => esc_html__('Columns', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => '3',
                'options' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                    '6' => '6',
                    '7' => '7',
                    '8' => '8',
                    '9' => '9',
                    '10' => '10',
                    '11' => '11',
                    '12' => '12',
                ],
                'prefix_class' => 'elemacy-grid-columns%s-',
                'selectors' => [
                    '{{WRAPPER}} .elemacy-loop-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
                ],
            ]
        );

        $this->add_responsive_control(
            'column_gap',
            [
                'label' => esc_html__('Column Gap', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem', '%'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-loop-grid' => 'column-gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'row_gap',
            [
                'label' => esc_html__('Row Gap', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem', '%'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-loop-grid' => 'row-gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function item_presenter(): ItemPresenterInterface
    {
        return Container::instance()->singleton(TemplateItemPresenter::class);
    }

    protected function render_loop(): void
    {
        $settings = $this->get_settings_for_display();

        if (empty($settings['template_id'])) {
            if (Plugin::instance()->editor->is_edit_mode()) {
                echo '<div class="elemacy-alert elemacy-alert-warning">' . esc_html__('Please select a template for the Loop Builder.', 'elemacy') . '</div>';
            }
            return;
        }

        $source = LoopDataSourceRegistry::instance()->get($settings['data_source'] ?? 'posts');

        if (!$source) {
            if (Plugin::instance()->editor->is_edit_mode()) {
                echo '<div class="elemacy-alert elemacy-alert-warning">' . esc_html__('The selected data source for this Loop Builder is not available.', 'elemacy') . '</div>';
            }
            return;
        }

        $page = $this->requested_page($settings, $source);
        $result = $this->items_for_display($settings, $source, $page);

        if (empty($result->items)) {
            $this->print_empty_notice();
            return;
        }

        $this->render_paginated_list($settings, $source, $result, $page);
    }
}
