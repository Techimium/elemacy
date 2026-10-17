<?php

namespace Elemacy\Modules\Widgets\Widgets;

use Elemacy\Core\Container;
use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Pagination\DTO\PublicLoopRequest;
use Elemacy\Loop\Pagination\PaginationContextSigner;
use Elemacy\Loop\Pagination\PaginationRenderer;
use Elemacy\Loop\Pagination\PublicRenderPolicy;
use Elemacy\Loop\Pagination\PublishedLoopDefinitionResolver;
use Elemacy\Loop\Rendering\LoopRenderer;
use Elemacy\Loop\Contracts\LoopDataSourceInterface;
use Elemacy\Loop\Contracts\PageUrlPaginatedSourceInterface;
use Elemacy\Loop\Placement\PlacementResolverRegistry;
use Elemacy\TemplateLibrary\DTO\BlockTemplateListFilterDTO;
use Elemacy\TemplateLibrary\Services\BlockTemplateService;
use Elementor\Controls_Manager;
use Elementor\Plugin;
use Elementor\Group_Control_Typography;
use Elemacy\Elementor\Widgets\BaseWidget;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class LoopGrid extends BaseWidget
{
    /**
     * Why visitors cannot see this loop, computed only for a user who can
     * edit the owning document; null when the loop is eligible.
     *
     * @var string|null
     */
    private ?string $ineligibility_notice = null;

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
            $this->loop_templates = Container::instance()->singleton(BlockTemplateService::class)->get_all_unbounded(
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

        // Normal Tab.
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

        // Hover Tab.
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

        // Active Tab.
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

    protected function render()
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

        $result = $this->items_for_display($settings, $source);

        if (empty($result->items)) {
            if (Plugin::instance()->editor->is_edit_mode()) {
                echo '<div class="elemacy-alert elemacy-alert-info">' . esc_html__('No posts found matching the current query.', 'elemacy') . '</div>';
            } elseif (null !== $this->ineligibility_notice) {
                echo '<div class="elemacy-loop-notice" role="status">' . esc_html($this->ineligibility_notice) . '</div>';
            }
            return;
        }

        $wrapper_classes = 'elemacy-loop-builder-grid';
        $wrapper_attrs = '';

        if (!empty($settings['pagination_ajax']) && $settings['pagination_ajax'] === 'yes') {
            $wrapper_classes .= ' elemacy-ajax-pagination';
            $wrapper_attrs .= $source instanceof PageUrlPaginatedSourceInterface
                ? $this->page_url_attrs()
                : $this->ajax_request_attrs();
        }

        echo '<div class="' . esc_attr($wrapper_classes) . '"' . $wrapper_attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $wrapper_attrs is built with esc_attr; remaining HTML structure is hardcoded
        echo '<div class="elemacy-loop-grid">';

        $renderer = Container::instance()->singleton(LoopRenderer::class);
        $placement = $this->current_owner_id() . '-' . $this->get_id();
        $data_source_key = $settings['data_source'] ?? 'posts';
        foreach ($result->items as $occurrence => $item) {
            $renderer->render_item((int) $settings['template_id'], $item, $placement, $data_source_key, $occurrence);
        }

        echo '</div>';

        if (!empty($settings['pagination_type']) && $result->max_num_pages > 1) {
            Container::instance()->singleton(PaginationRenderer::class)->render($settings, $result->max_num_pages);
        }

        echo '</div>'; // End elemacy-loop-builder-grid.
    }

    /**
     * The document actually rendering this widget right now — a Theme
     * Builder header/footer, popup, or loop template document Elementor has
     * switched to, not the viewed page (get_the_ID()), when this widget
     * lives in one of those.
     */
    private function current_owner_id(): int
    {
        $document = Plugin::$instance->documents->get_current();

        return $document ? (int) $document->get_main_id() : (int) get_the_ID();
    }

    /**
     * Applies the same public eligibility policy the AJAX endpoint enforces to
     * this widget's own classic first-page output, so an authored
     * template/source that has since become inaccessible cannot appear there
     * either.
     *
     * @param array                   $settings
     * @param LoopDataSourceInterface $source
     * @return LoopResultDTO
     */
    private function items_for_display(array $settings, LoopDataSourceInterface $source): LoopResultDTO
    {
        if (Plugin::instance()->editor->is_edit_mode()) {
            return $source->get_items($settings);
        }

        $policy = Container::instance()->singleton(PublicRenderPolicy::class);
        $owner_id = $this->current_owner_id();

        try {
            return $policy->eligible_items($settings, $owner_id);
        } catch (ValidationException $error) {
            if (current_user_can('edit_post', $owner_id)) {
                $this->ineligibility_notice = $policy->describe_ineligibility($settings);
            }

            return new LoopResultDTO([], 0, 0, false);
        }
    }

    /**
     * Marks a page-URL-paginated loop (Current Query) for the frontend script:
     * it fetches the clicked pagination link's own URL and swaps this loop,
     * found again in the response by this identity. Nothing here is a
     * request the server replays, so there is no signed payload.
     */
    private function page_url_attrs(): string
    {
        return sprintf(
            ' data-elemacy-loop-mode="page" data-elemacy-loop-id="%s"',
            esc_attr($this->current_owner_id() . '-' . $this->get_id())
        );
    }

    /**
     * The signed request envelope subsequent AJAX pages replay: owner/widget
     * identity and definition revision, resolved and signed the same way
     * PublishedLoopDefinitionResolver/PaginationContextSigner verify them,
     * plus an independently-verified host-context token when a contributed
     * placement resolver recognizes the current request.
     */
    private function ajax_request_attrs(): string
    {
        try {
            $policy = Container::instance()->singleton(PublicRenderPolicy::class);
            $definition = Container::instance()->singleton(PublishedLoopDefinitionResolver::class)->find($this->current_owner_id(), $this->get_id());

            $context = '';
            $placement = PlacementResolverRegistry::instance()->resolve_current($definition->owner_id());
            if (null !== $placement) {
                $context = Container::instance()->singleton(PaginationContextSigner::class)->sign(
                    $definition->owner_id(),
                    $definition->widget_id(),
                    $definition->fingerprint(),
                    $placement['type'],
                    $placement['facts']
                );
            }

            $payload = wp_json_encode([
                'version' => PublicLoopRequest::VERSION,
                'owner_id' => $definition->owner_id(),
                'widget_id' => $definition->widget_id(),
                'page' => 1,
                'fingerprint' => $definition->fingerprint(),
                'context' => $context,
            ]);
        } catch (ValidationException $error) {
            return '';
        }

        return sprintf(
            ' data-elemacy-loop-request="%s" data-elemacy-loop-base="%s"',
            esc_attr($payload),
            esc_attr(str_replace('999999999', '%#%', get_pagenum_link(999999999, false)))
        );
    }
}
