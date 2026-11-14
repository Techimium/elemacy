<?php

namespace Elemacy\Modules\LoopBuilder\PostCard\Parts;

defined('ABSPATH') || exit;

use Elemacy\Modules\LoopBuilder\PostCard\PostCardContext;
use Elemacy\Modules\LoopBuilder\PostCard\PostCardText;
use Elemacy\Widgets\BaseWidget;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use WP_Post;

/**
 * A manual excerpt, or a trimmed version of the post's own text.
 *
 * @internal
 */
final class ExcerptPart extends AbstractPostCardPart
{
    public function key(): string
    {
        return 'excerpt';
    }

    public function label(): string
    {
        return esc_html__('Excerpt', 'elemacy');
    }

    public function defaults(): array
    {
        return [
            'excerpt_length' => 25,
            'excerpt_unit' => 'words',
            'excerpt_trim_manual' => '',
        ];
    }

    public function register_row_controls(Repeater $rows, array $condition): void
    {
        $defaults = $this->defaults();

        $rows->add_control(
            'excerpt_length',
            [
                'label' => esc_html__('Length', 'elemacy'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'default' => $defaults['excerpt_length'],
                'condition' => $condition,
            ]
        );

        $rows->add_control(
            'excerpt_unit',
            [
                'label' => esc_html__('Count', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'words' => esc_html__('Words', 'elemacy'),
                    'characters' => esc_html__('Characters', 'elemacy'),
                ],
                'default' => $defaults['excerpt_unit'],
                'condition' => $condition,
            ]
        );

        $rows->add_control(
            'excerpt_trim_manual',
            [
                'label' => esc_html__('Apply to Manual Excerpts', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => $defaults['excerpt_trim_manual'],
                'condition' => $condition,
            ]
        );
    }

    public function register_style_section(BaseWidget $widget): void
    {
        $this->open_style_section($widget);
        $this->add_text_style_controls($widget, '.elemacy-post-card__part--excerpt');
        $this->add_line_limit_control($widget, '.elemacy-post-card__part--excerpt');
        $widget->end_controls_section();
    }

    public function render(WP_Post $post, array $row, PostCardContext $card): string
    {
        if (post_password_required($post)) {
            return '';
        }

        $manual = trim(wp_strip_all_tags($post->post_excerpt));
        $text = '' !== $manual ? $manual : PostCardText::plain_content($post);

        if ('' === $text) {
            return '';
        }

        if ('' === $manual || 'yes' === $row['excerpt_trim_manual']) {
            $text = PostCardText::trim($text, (int) $row['excerpt_length'], (string) $row['excerpt_unit']);
        }

        return esc_html($text);
    }
}
