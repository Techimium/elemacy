<?php

namespace Elemacy\Modules\LoopBuilder\Widgets\PostCarousel;

use Elemacy\Loop\Contracts\LoopDataSourceInterface;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Modules\LoopBuilder\Widgets\Controls\SliderSection;
use Elemacy\Modules\LoopBuilder\Widgets\Controls\SliderStyleSection;
use Elemacy\Modules\LoopBuilder\Widgets\PostWidget;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Posts as a slider of cards, with no template to build.
 *
 * @internal
 */
class PostCarousel extends PostWidget
{
    public function get_name()
    {
        return 'elemacy-post-carousel';
    }

    public function get_title()
    {
        return esc_html__('Post Carousel', 'elemacy');
    }

    public function get_icon()
    {
        return 'eicon-posts-carousel';
    }

    public function get_keywords()
    {
        return ['posts', 'carousel', 'slider', 'blog', 'custom post type', 'cpt'];
    }

    public function get_style_depends()
    {
        return ['elemacy-loop-carousel', 'elemacy-post-card'];
    }

    public function get_script_depends()
    {
        return ['elemacy-loop-carousel', 'elemacy-post-carousel'];
    }

    public function items_count_label(): string
    {
        return esc_html__('Posts to Show', 'elemacy');
    }

    protected function register_controls()
    {
        $this->register_layout_section();
        $this->register_query_section();
        $this->register_card_content_section();
        SliderSection::register($this);
        $this->register_card_style_sections();
        SliderStyleSection::register($this);
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

        $this->register_layout_page_size();
        SliderSection::register_slide_controls($this);
        $this->update_control('equal_height', ['default' => 'yes']);

        $this->end_controls_section();
    }

    protected function render_list(array $settings, LoopDataSourceInterface $source, LoopResultDTO $result, int $page): void
    {
        $this->render_slider($settings, $result);
    }
}
