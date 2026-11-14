<?php

namespace Elemacy\Modules\LoopBuilder\PostCard\Parts;

defined('ABSPATH') || exit;

use Elemacy\Modules\LoopBuilder\PostCard\PostCardContext;
use Elemacy\Widgets\BaseWidget;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use WP_Post;

/**
 * The post's title.
 *
 * @internal
 */
final class TitlePart extends AbstractPostCardPart
{
    private const TAGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p', 'span'];

    public function key(): string
    {
        return 'title';
    }

    public function label(): string
    {
        return esc_html__('Title', 'elemacy');
    }

    public function defaults(): array
    {
        return [
            'title_tag' => 'h3',
            'title_link' => 'yes',
        ];
    }

    public function register_row_controls(Repeater $rows, array $condition): void
    {
        $defaults = $this->defaults();

        $rows->add_control(
            'title_tag',
            [
                'label' => esc_html__('HTML Tag', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => array_combine(self::TAGS, self::TAGS),
                'default' => $defaults['title_tag'],
                'condition' => $condition,
            ]
        );

        $rows->add_control(
            'title_link',
            [
                'label' => esc_html__('Link to Post', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => $defaults['title_link'],
                'condition' => $condition,
            ]
        );
    }

    public function register_style_section(BaseWidget $widget): void
    {
        $this->open_style_section($widget);
        $this->add_text_style_controls($widget, '.elemacy-post-card__part--title');
        $this->add_line_limit_control($widget, '.elemacy-post-card__title');
        $widget->end_controls_section();
    }

    public function render(WP_Post $post, array $row, PostCardContext $card): string
    {
        $title = get_the_title($post);

        if ('' === $title) {
            return '';
        }

        $tag = in_array($row['title_tag'], self::TAGS, true) ? $row['title_tag'] : 'h3';
        $text = esc_html($title);

        if ('yes' === $row['title_link']) {
            $text = '<a class="elemacy-post-card__link" href="' . esc_url(get_permalink($post)) . '"' . $card->link_attributes() . '>' . $text . '</a>';
        }

        return '<' . $tag . ' class="elemacy-post-card__title">' . $text . '</' . $tag . '>';
    }
}
