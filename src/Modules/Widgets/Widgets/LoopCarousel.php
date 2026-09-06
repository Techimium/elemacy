<?php

namespace Elemacy\Modules\Widgets\Widgets;

use Elemacy\Modules\Widgets\Services\LoopContext;
use Elemacy\Modules\Widgets\Services\LoopDataSourceRegistry;
use Elemacy\Modules\Widgets\Services\LoopItemStyles;
use Elemacy\TemplateLibrary\DTO\BlockTemplateListFilterDTO;
use Elemacy\TemplateLibrary\Services\BlockTemplateService;
use Elementor\Controls_Manager;
use Elementor\Plugin;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class LoopCarousel extends BaseWidget
{
    public function get_name()
    {
        return 'elemacy-loop-carousel';
    }

    public function get_title()
    {
        return esc_html__('Loop Carousel', 'elemacy');
    }

    public function get_icon()
    {
        return 'eicon-carousel';
    }

    public function get_keywords()
    {
        return ['loop', 'carousel', 'slider', 'post', 'custom post type', 'dynamic'];
    }

    public function get_style_depends()
    {
        return ['elemacy-loop-carousel'];
    }

    public function get_script_depends()
    {
        return ['elemacy-loop-carousel'];
    }

    protected function register_controls()
    {
        $this->register_layout_section();
        $this->register_query_section();
        $this->register_carousel_settings_section();
        $this->register_carousel_style_section();
        // Since pagination in carousel is handled by swiper, we add its styles
        $this->register_navigation_style_section();
        $this->register_pagination_style_section();
    }

    private $loop_templates;

    protected function get_loop_templates()
    {
        if ($this->loop_templates === null) {
            $this->loop_templates = (new BlockTemplateService())->get_all_unbounded(
                BlockTemplateListFilterDTO::from_array(['type' => 'loop'])
            );
        }

        return $this->loop_templates;
    }

    protected function get_elementor_templates()
    {
        $options = [
            '' => esc_html__('Select Template', 'elemacy'),
        ];

        foreach ($this->get_loop_templates() as $template) {
            $options[$template->id] = $template->title;
        }

        return $options;
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

        $this->add_responsive_control(
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

        $this->add_control(
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

        $this->end_controls_section();
    }

    protected function register_query_section()
    {
        $this->start_controls_section(
            'section_query',
            [
                'label' => esc_html__('Query', 'elemacy'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $registry = LoopDataSourceRegistry::instance();

        $options = [];
        foreach ($registry->all() as $key => $source) {
            $options[$key] = $source->get_label();
        }

        $this->add_control(
            'data_source',
            [
                'label' => esc_html__('Data Source', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => $options,
                'default' => 'posts',
            ]
        );

        // Every registered source's controls are always registered; each
        // scopes its own visibility with a `data_source` condition, the
        // same way `post_type!` conditions already worked before this.
        foreach ($registry->all() as $source) {
            $source->register_controls($this);
        }

        $this->end_controls_section();
    }

    protected function register_carousel_settings_section()
    {
        $this->start_controls_section(
            'section_carousel_settings',
            [
                'label' => esc_html__('Carousel Settings', 'elemacy'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'autoplay',
            [
                'label' => esc_html__('Autoplay', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'frontend_available' => true,
            ]
        );

        $this->add_control(
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

        $this->add_control(
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

        $this->add_control(
            'loop',
            [
                'label' => esc_html__('Infinite Loop', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'frontend_available' => true,
            ]
        );

        $this->add_control(
            'transition_speed',
            [
                'label' => esc_html__('Transition Speed (ms)', 'elemacy'),
                'type' => Controls_Manager::NUMBER,
                'default' => 500,
                'frontend_available' => true,
            ]
        );

        $this->add_control(
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

        $this->end_controls_section();
    }

    protected function register_carousel_style_section()
    {
        $this->start_controls_section(
            'section_carousel_style',
            [
                'label' => esc_html__('Carousel', 'elemacy'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
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

        $this->end_controls_section();
    }

    protected function register_navigation_style_section()
    {
        $this->start_controls_section(
            'section_navigation_style',
            [
                'label' => esc_html__('Navigation Arrows', 'elemacy'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'navigation' => ['both', 'arrows'],
                ],
            ]
        );

        $this->add_control(
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

        $this->add_control(
            'arrows_color',
            [
                'label' => esc_html__('Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-swiper-button' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'arrows_bg_color',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-swiper-button' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
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

        $this->add_control(
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

        $this->end_controls_section();
    }

    protected function register_pagination_style_section()
    {
        $this->start_controls_section(
            'section_pagination_style',
            [
                'label' => esc_html__('Pagination', 'elemacy'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'navigation' => ['both', 'pagination'],
                ],
            ]
        );

        $this->add_control(
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

        $this->add_responsive_control(
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

        $this->add_control(
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

        $this->add_control(
            'pagination_color',
            [
                'label' => esc_html__('Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .swiper-pagination-bullet' => 'background: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'pagination_active_color',
            [
                'label' => esc_html__('Active Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .swiper-pagination-bullet-active' => 'background: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();

        if (empty($settings['template_id'])) {
            if (Plugin::instance()->editor->is_edit_mode()) {
                echo '<div class="elemacy-alert elemacy-alert-warning">' . esc_html__('Please select a template for the Loop Carousel.', 'elemacy') . '</div>';
            }
            return;
        }

        $source = LoopDataSourceRegistry::instance()->get($settings['data_source'] ?? 'posts');

        if (!$source) {
            if (Plugin::instance()->editor->is_edit_mode()) {
                echo '<div class="elemacy-alert elemacy-alert-warning">' . esc_html__('The selected data source for this Loop Carousel is not available.', 'elemacy') . '</div>';
            }
            return;
        }

        $result = $source->get_items($settings);

        if (empty($result->items)) {
            if (Plugin::instance()->editor->is_edit_mode()) {
                echo '<div class="elemacy-alert elemacy-alert-info">' . esc_html__('No posts found matching the current query.', 'elemacy') . '</div>';
            }
            return;
        }

        echo '<div class="elemacy-loop-carousel-container">';
        echo '<div class="swiper elemacy-loop-carousel">';
        echo '<div class="swiper-wrapper">';

        $loop_item_styles = new LoopItemStyles();
        $loop_item_styles->print_base_css($settings['template_id']);

        foreach ($result->items as $item) {
            LoopContext::push($item);

            try {
                $item->enter();

                try {
                    $loop_item_styles->print_item_css($settings['template_id'], $item->get_identity());

                    echo '<div class="swiper-slide">';
                    echo '<div class="elemacy-loop-item elemacy-loop-item-' . esc_attr($item->get_identity()) . '">';
                    echo Plugin::instance()->frontend->get_builder_content_for_display($settings['template_id']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's get_builder_content_for_display() returns fully-rendered and escaped HTML
                    echo '</div>';
                    echo '</div>';
                } finally {
                    $item->exit();
                }
            } finally {
                LoopContext::pop();
            }
        }

        echo '</div>'; // End swiper-wrapper

        // Render Navigation Elements
        if (in_array($settings['navigation'], ['both', 'pagination'], true)) {
            echo '<div class="swiper-pagination"></div>';
        }

        if (in_array($settings['navigation'], ['both', 'arrows'], true)) {
            echo '<div class="elemacy-swiper-button elemacy-swiper-button-prev swiper-button-prev"><i class="eicon-chevron-left"></i></div>';
            echo '<div class="elemacy-swiper-button elemacy-swiper-button-next swiper-button-next"><i class="eicon-chevron-right"></i></div>';
        }

        echo '</div>'; // End swiper element
        echo '</div>'; // End elemacy-loop-carousel-container
    }
}
