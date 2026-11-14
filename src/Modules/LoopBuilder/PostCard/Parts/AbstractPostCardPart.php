<?php

namespace Elemacy\Modules\LoopBuilder\PostCard\Parts;

defined('ABSPATH') || exit;

use Elemacy\Modules\LoopBuilder\Contracts\PostCardPartInterface;
use Elemacy\Widgets\BaseWidget;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

/**
 * What most parts share: a plain text style section and no row grouping.
 *
 * @internal
 */
abstract class AbstractPostCardPart implements PostCardPartInterface
{
    public function groups_adjacent_rows(): bool
    {
        return false;
    }

    public function join(array $row): string
    {
        return '';
    }

    public function register_style_section(BaseWidget $widget): void
    {
        $this->open_style_section($widget);
        $this->add_text_style_controls($widget, '.elemacy-post-card__part--' . $this->key());
        $widget->end_controls_section();
    }

    protected function open_style_section(BaseWidget $widget): void
    {
        $widget->start_controls_section(
            'section_card_' . $this->key() . '_style',
            [
                'label' => $this->label(),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );
    }

    /**
     * @param BaseWidget $widget
     * @param string     $selector The part's CSS selector.
     * @return void
     */
    protected function add_text_style_controls(BaseWidget $widget, string $selector): void
    {
        $prefix = 'card_' . $this->key() . '_';
        $target = '{{WRAPPER}} ' . $selector;

        $widget->add_control(
            $prefix . 'color',
            [
                'label' => esc_html__('Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [$target . ', ' . $target . ' a' => 'color: {{VALUE}};'],
            ]
        );

        $widget->add_control(
            $prefix . 'hover_color',
            [
                'label' => esc_html__('Link Hover Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [$target . ' a:hover' => 'color: {{VALUE}};'],
            ]
        );

        $widget->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => $prefix . 'typography',
                'selector' => $target . ', ' . $target . ' a',
            ]
        );

        $widget->add_responsive_control(
            $prefix . 'align',
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
                'selectors' => [$target => 'text-align: {{VALUE}};'],
            ]
        );

        $widget->add_responsive_control(
            $prefix . 'spacing',
            [
                'label' => esc_html__('Spacing', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors' => [$target => 'margin-bottom: {{SIZE}}{{UNIT}};'],
            ]
        );
    }

    /** A control limiting a selector to a number of lines per device. */
    protected function add_line_limit_control(BaseWidget $widget, string $selector): void
    {
        $widget->add_responsive_control(
            'card_' . $this->key() . '_lines',
            [
                'label' => esc_html__('Maximum Lines', 'elemacy'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'selectors' => ['{{WRAPPER}} ' . $selector => 'display: -webkit-box; -webkit-box-orient: vertical; overflow: hidden; -webkit-line-clamp: {{VALUE}};'],
            ]
        );
    }
}
