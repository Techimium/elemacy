<?php

namespace Elemacy\Modules\Widgets\Widgets;

use Elemacy\Core\Constants\PostStatus;
use Elemacy\TemplateLibrary\DTO\BlockTemplateListFilterDTO;
use Elemacy\TemplateLibrary\Services\BlockTemplateService;
use Elementor\Controls_Manager;
use Elementor\Plugin;
use Elementor\Group_Control_Typography;
use WP_Query;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class LoopGrid extends BaseWidget
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
        return ['elemacy-loop-grid'];
    }

    protected function register_controls()
    {
        $this->register_layout_section();
        $this->register_query_section();
        $this->register_pagination_section();
        $this->register_pagination_style_section();
    }

    private $loop_templates;

    protected function get_loop_templates()
    {
        if ($this->loop_templates === null) {
            $this->loop_templates = (new BlockTemplateService())->get_all(
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

    protected function get_public_post_types()
    {
        $post_types = get_post_types(['public' => true], 'objects');
        $options = [
            'current_query' => esc_html__('Current Query', 'elemacy'),
        ];

        foreach ($post_types as $post_type) {
            $options[$post_type->name] = $post_type->label;
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

    protected function register_query_section()
    {
        $this->start_controls_section(
            'section_query',
            [
                'label' => esc_html__('Query', 'elemacy'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'post_type',
            [
                'label' => esc_html__('Source', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_public_post_types(),
                'default' => 'post',
            ]
        );

        $this->add_control(
            'posts_per_page',
            [
                'label' => esc_html__('Posts Per Page', 'elemacy'),
                'type' => Controls_Manager::NUMBER,
                'default' => 6,
                'condition' => [
                    'post_type!' => 'current_query',
                ],
            ]
        );

        $this->add_control(
            'offset',
            [
                'label' => esc_html__('Offset', 'elemacy'),
                'type' => Controls_Manager::NUMBER,
                'default' => 0,
                'description' => esc_html__('Number of posts to skip. Note: Using an offset can break pagination.', 'elemacy'),
                'condition' => [
                    'post_type!' => 'current_query',
                ],
            ]
        );

        $this->add_control(
            'orderby',
            [
                'label' => esc_html__('Order By', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => 'date',
                'options' => [
                    'date' => esc_html__('Date', 'elemacy'),
                    'title' => esc_html__('Title', 'elemacy'),
                    'menu_order' => esc_html__('Menu Order', 'elemacy'),
                    'rand' => esc_html__('Random', 'elemacy'),
                ],
                'condition' => [
                    'post_type!' => 'current_query',
                ],
            ]
        );

        $this->add_control(
            'order',
            [
                'label' => esc_html__('Order', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => 'DESC',
                'options' => [
                    'ASC' => esc_html__('ASC', 'elemacy'),
                    'DESC' => esc_html__('DESC', 'elemacy'),
                ],
                'condition' => [
                    'post_type!' => 'current_query',
                ],
            ]
        );

        $this->add_control(
            'exclude_current_post',
            [
                'label' => esc_html__('Exclude Current Post', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => [
                    'post_type!' => 'current_query',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function register_pagination_section()
    {
        $this->start_controls_section(
            'section_pagination',
            [
                'label' => esc_html__('Pagination', 'elemacy'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'pagination_type',
            [
                'label' => esc_html__('Pagination', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => '',
                'options' => [
                    '' => esc_html__('None', 'elemacy'),
                    'numbers' => esc_html__('Numbers', 'elemacy'),
                    'prev_next' => esc_html__('Previous/Next', 'elemacy'),
                    'numbers_and_prev_next' => esc_html__('Numbers + Previous/Next', 'elemacy'),
                ],
            ]
        );

        $this->add_control(
            'pagination_ajax',
            [
                'label' => esc_html__('AJAX Pagination', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => 'no',
                'description' => esc_html__('Fetch the next page without reloading the entire page.', 'elemacy'),
                'condition' => [
                    'pagination_type!' => '',
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
            ]
        );

        $this->add_responsive_control(
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

        $this->add_responsive_control(
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

        $this->add_responsive_control(
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

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'pagination_typography',
                'selector' => '{{WRAPPER}} .elemacy-pagination .page-numbers',
            ]
        );

        $this->start_controls_tabs('pagination_style_tabs');

        // Normal Tab
        $this->start_controls_tab('pagination_style_normal', ['label' => esc_html__('Normal', 'elemacy')]);

        $this->add_control(
            'pagination_color',
            [
                'label' => esc_html__('Text Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'pagination_bg_color',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'pagination_border_color',
            [
                'label' => esc_html__('Border Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        // Hover Tab
        $this->start_controls_tab('pagination_style_hover', ['label' => esc_html__('Hover', 'elemacy')]);

        $this->add_control(
            'pagination_color_hover',
            [
                'label' => esc_html__('Text Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'pagination_bg_color_hover',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'pagination_border_color_hover',
            [
                'label' => esc_html__('Border Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers:hover' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        // Active Tab
        $this->start_controls_tab('pagination_style_active', ['label' => esc_html__('Active', 'elemacy')]);

        $this->add_control(
            'pagination_color_active',
            [
                'label' => esc_html__('Text Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers.current' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'pagination_bg_color_active',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers.current' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'pagination_border_color_active',
            [
                'label' => esc_html__('Border Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-pagination .page-numbers.current' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_control(
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

        $this->add_control(
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

        $this->add_responsive_control(
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

        $this->end_controls_section();
    }

    protected function build_query_args($settings)
    {
        $args = [
            'post_type' => $settings['post_type'],
            'posts_per_page' => $settings['posts_per_page'],
            'orderby' => $settings['orderby'],
            'order' => $settings['order'],
            'post_status' => PostStatus::PUBLISH,
        ];

        if (!empty($settings['offset'])) {
            $args['offset'] = $settings['offset'];
        }

        if ('yes' === $settings['exclude_current_post'] && is_singular()) {
            $args['post__not_in'] = [get_the_ID()];
        }

        if (!empty($settings['pagination_type']) && empty($settings['offset'])) {
            $paged = get_query_var('paged') ? get_query_var('paged') : (get_query_var('page') ? get_query_var('page') : 1);
            $args['paged'] = $paged;
        }

        return $args;
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();

        if (empty($settings['template_id'])) {
            if (Plugin::instance()->editor->is_edit_mode()) {
                echo '<div class="elemacy-alert elemacy-alert-warning">' . esc_html__('Please select a template for the Loop Builder.', 'elemacy') . '</div>';
            }
            return;
        }

        if ($settings['post_type'] === 'current_query') {
            global $wp_query;
            $query = $wp_query;
        } else {
            $query_args = $this->build_query_args($settings);
            $query = new WP_Query($query_args);
        }

        if (!$query->have_posts()) {
            if (Plugin::instance()->editor->is_edit_mode()) {
                echo '<div class="elemacy-alert elemacy-alert-info">' . esc_html__('No posts found matching the current query.', 'elemacy') . '</div>';
            }
            return;
        }

        global $post;

        $wrapper_classes = 'elemacy-loop-builder-grid';
        $wrapper_attrs = '';

        if (!empty($settings['pagination_ajax']) && $settings['pagination_ajax'] === 'yes') {
            $wrapper_classes .= ' elemacy-ajax-pagination';

            $ajax_settings = [
                'post_type' => $settings['post_type'],
                'posts_per_page' => $settings['posts_per_page'],
                'orderby' => $settings['orderby'],
                'order' => $settings['order'],
                'offset' => $settings['offset'],
                'exclude_current_post' => $settings['exclude_current_post'],
                'template_id' => $settings['template_id'],
                'pagination_type' => $settings['pagination_type'],
                'current_post_id' => get_the_ID(),
                'paginate_base' => str_replace('999999999', '%#%', get_pagenum_link(999999999, false)),
            ];

            if ($settings['post_type'] === 'current_query') {
                global $wp_query;
                $ajax_settings['current_query_vars'] = $wp_query->query_vars;
            }

            $settings_json = wp_json_encode($ajax_settings);

            $wrapper_attrs = sprintf(
                ' data-elemacy-loop-settings="%s"',
                esc_attr($settings_json)
            );
        }

        echo '<div class="' . esc_attr($wrapper_classes) . '"' . $wrapper_attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $wrapper_attrs is built with esc_attr(); remaining HTML structure is hardcoded
        echo '<div class="elemacy-loop-grid">';

        // WP 6.9+ rejects styles whose dependencies aren't yet registered (elementor-post-{id}
        // depends on elementor-frontend). register_styles() is normally on wp_enqueue_scripts
        // but may not have run yet in non-Elementor host pages or AJAX contexts.
        if (!wp_style_is('elementor-frontend', 'registered')) {
            Plugin::instance()->frontend->register_styles();
        }

        while ($query->have_posts()) {
            $query->the_post();

            echo '<div class="elemacy-loop-item elemacy-loop-item-' . esc_attr(get_the_ID()) . '">';
            echo Plugin::instance()->frontend->get_builder_content_for_display($settings['template_id']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's get_builder_content_for_display() returns fully-rendered and escaped HTML
            echo '</div>';
        }

        wp_reset_postdata();

        echo '</div>';

        if (!empty($settings['pagination_type']) && $query->max_num_pages > 1) {
            static::render_pagination_html($settings, $query);
        }

        echo '</div>'; // End elemacy-loop-builder-grid
    }

    public static function render_pagination_html($settings, $query, $current_page = null)
    {
        if ($current_page === null) {
            $current_page = max(1, get_query_var('paged'), get_query_var('page'));
        }
        $show_all = false;
        $prev_next = false;
        $prev_text = esc_html__('« Previous', 'elemacy');
        $next_text = esc_html__('Next »', 'elemacy');
        $show_numbers = false;

        switch ($settings['pagination_type']) {
            case 'numbers':
                $show_numbers = true;
                break;
            case 'prev_next':
                $prev_next = true;
                break;
            case 'numbers_and_prev_next':
                $show_numbers = true;
                $prev_next = true;
                break;
        }

        $paginate_args = [
            'total' => $query->max_num_pages,
            'current' => $current_page,
            'prev_next' => $prev_next,
            'prev_text' => $prev_text,
            'next_text' => $next_text,
            'show_all' => $show_all,
            'type' => 'plain',
        ];

        if (!empty($settings['paginate_base'])) {
            $paginate_args['base'] = $settings['paginate_base'];
        }

        if (!$show_numbers && $prev_next) {
            $paginate_args['prev_next'] = true;
        }

        $links = paginate_links($paginate_args);

        if ($links) {
            echo '<nav class="elemacy-pagination" role="navigation">';
            echo wp_kses_post($links);
            echo '</nav>';
        }
    }
}
