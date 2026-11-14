<?php

namespace Elemacy\Modules\LoopBuilder\Widgets\LoopCarousel;

use Elemacy\Core\Container;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Contracts\ItemPresenterInterface;
use Elemacy\Loop\Rendering\TemplateItemPresenter;
use Elementor\Controls_Manager;
use Elementor\Plugin;
use Elemacy\Modules\LoopBuilder\Widgets\Controls\SliderSection;
use Elemacy\Modules\LoopBuilder\Widgets\Controls\SliderStyleSection;
use Elemacy\Modules\LoopBuilder\Widgets\LoopWidget;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * @internal
 */
class LoopCarousel extends LoopWidget
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
        SliderSection::register($this);
        SliderStyleSection::register($this);
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

        SliderSection::register_slide_controls($this);

        $this->end_controls_section();
    }

    public function items_count_label(): string
    {
        return esc_html__('Posts Count', 'elemacy');
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

        $result = $this->items_for_display($settings, $source);

        if (empty($result->items)) {
            $this->print_empty_notice();
            return;
        }

        $this->render_slider($settings, $result);
    }
}
