<?php

namespace Elemacy\Modules\LoopBuilder\PostCard\Parts;

defined('ABSPATH') || exit;

use Elemacy\Modules\LoopBuilder\PostCard\PostCardContext;
use Elemacy\Modules\LoopBuilder\PostCard\PostCardText;
use Elemacy\Widgets\BaseWidget;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Repeater;
use WP_Post;

/**
 * A link to the post, named for screen readers after the post it opens.
 *
 * @internal
 */
final class ButtonPart extends AbstractPostCardPart
{
    private const SELECTOR = '{{WRAPPER}} .elemacy-post-card__button';

    public function key(): string
    {
        return 'button';
    }

    public function label(): string
    {
        return esc_html__('Button', 'elemacy');
    }

    public function defaults(): array
    {
        return [
            'button_text' => __('Read More', 'elemacy'),
            'button_icon' => [
                'value' => '',
                'library' => '',
            ],
            'button_icon_position' => 'after',
        ];
    }

    public function register_row_controls(Repeater $rows, array $condition): void
    {
        $defaults = $this->defaults();

        $rows->add_control(
            'button_text',
            [
                'label' => esc_html__('Text', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => $defaults['button_text'],
                'condition' => $condition,
            ]
        );

        $rows->add_control(
            'button_icon',
            [
                'label' => esc_html__('Icon', 'elemacy'),
                'type' => Controls_Manager::ICONS,
                'default' => $defaults['button_icon'],
                'condition' => $condition,
            ]
        );

        $rows->add_control(
            'button_icon_position',
            [
                'label' => esc_html__('Icon Position', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'before' => esc_html__('Before', 'elemacy'),
                    'after' => esc_html__('After', 'elemacy'),
                ],
                'default' => $defaults['button_icon_position'],
                'condition' => $condition,
            ]
        );
    }

    public function register_style_section(BaseWidget $widget): void
    {
        $this->open_style_section($widget);

        $widget->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'card_button_typography',
                'selector' => self::SELECTOR,
            ]
        );

        foreach (
            [
                'card_button_color' => [esc_html__('Text Color', 'elemacy'), self::SELECTOR, 'color: {{VALUE}};'],
                'card_button_background' => [esc_html__('Background', 'elemacy'), self::SELECTOR, 'background-color: {{VALUE}};'],
                'card_button_hover_color' => [esc_html__('Hover Text Color', 'elemacy'), self::SELECTOR . ':hover', 'color: {{VALUE}};'],
                'card_button_hover_background' => [esc_html__('Hover Background', 'elemacy'), self::SELECTOR . ':hover', 'background-color: {{VALUE}};'],
            ] as $id => [$label, $selector, $css]
        ) {
            $widget->add_control(
                $id,
                [
                    'label' => $label,
                    'type' => Controls_Manager::COLOR,
                    'selectors' => [$selector => $css],
                ]
            );
        }

        $widget->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'card_button_border',
                'selector' => self::SELECTOR,
            ]
        );

        $widget->add_responsive_control(
            'card_button_radius',
            [
                'label' => esc_html__('Border Radius', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [self::SELECTOR => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $widget->add_responsive_control(
            'card_button_padding',
            [
                'label' => esc_html__('Padding', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [self::SELECTOR => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $widget->add_responsive_control(
            'card_button_align',
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
                'selectors' => ['{{WRAPPER}} .elemacy-post-card__part--button' => 'text-align: {{VALUE}};'],
            ]
        );

        $widget->end_controls_section();
    }

    public function render(WP_Post $post, array $row, PostCardContext $card): string
    {
        $label = trim((string) $row['button_text']);

        if ('' === $label) {
            return '';
        }

        $text = esc_html($label);
        $icon = $this->icon($row);
        $content = 'before' === $row['button_icon_position'] ? $icon . $text : $text . $icon;

        return '<a class="elemacy-post-card__button" href="' . esc_url(get_permalink($post)) . '"' . $card->link_attributes() . '>'
            . $content
            . '<span class="elemacy-post-card__sr">: ' . esc_html(PostCardText::link_name($post)) . '</span>'
            . '</a>';
    }

    private function icon(array $row): string
    {
        if (!is_array($row['button_icon']) || empty($row['button_icon']['value'])) {
            return '';
        }

        ob_start();
        Icons_Manager::render_icon($row['button_icon'], ['aria-hidden' => 'true']);

        return (string) ob_get_clean();
    }
}
