<?php

namespace Elemacy\Modules\LoopBuilder\PostCard\Parts;

defined('ABSPATH') || exit;

use Elemacy\Modules\LoopBuilder\PostCard\PostCardContext;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use WP_Post;

/**
 * The post's author: avatar and name.
 *
 * @internal
 */
final class AuthorPart extends AbstractPostCardPart
{
    public function key(): string
    {
        return 'author';
    }

    public function label(): string
    {
        return esc_html__('Author', 'elemacy');
    }

    public function defaults(): array
    {
        return [
            'author_avatar' => 'yes',
            'author_avatar_size' => 40,
            'author_name' => 'yes',
            'author_link' => '',
        ];
    }

    public function register_row_controls(Repeater $rows, array $condition): void
    {
        $defaults = $this->defaults();

        foreach (
            [
                'author_avatar' => esc_html__('Show Avatar', 'elemacy'),
                'author_name' => esc_html__('Show Name', 'elemacy'),
                'author_link' => esc_html__('Link Name to Archive', 'elemacy'),
            ] as $id => $label
        ) {
            $rows->add_control(
                $id,
                [
                    'label' => $label,
                    'type' => Controls_Manager::SWITCHER,
                    'return_value' => 'yes',
                    'default' => $defaults[$id],
                    'condition' => $condition,
                ]
            );
        }

        $rows->add_control(
            'author_avatar_size',
            [
                'label' => esc_html__('Avatar Size', 'elemacy'),
                'type' => Controls_Manager::NUMBER,
                'min' => 16,
                'max' => 256,
                'default' => $defaults['author_avatar_size'],
                'condition' => $condition + ['author_avatar' => 'yes'],
            ]
        );
    }

    public function render(WP_Post $post, array $row, PostCardContext $card): string
    {
        if (!post_type_supports($post->post_type, 'author') || !$post->post_author) {
            return '';
        }

        $author = (int) $post->post_author;
        $html = 'yes' === $row['author_avatar'] ? (string) get_avatar($author, (int) $row['author_avatar_size']) : '';

        return $html . $this->name($author, $row, $card);
    }

    private function name(int $author, array $row, PostCardContext $card): string
    {
        $name = 'yes' === $row['author_name'] ? (string) get_the_author_meta('display_name', $author) : '';

        if ('' === $name) {
            return '';
        }

        $text = esc_html($name);

        if ('yes' === $row['author_link']) {
            $text = '<a href="' . esc_url(get_author_posts_url($author)) . '"' . $card->link_attributes() . '>' . $text . '</a>';
        }

        return '<span class="elemacy-post-card__author-name">' . $text . '</span>';
    }
}
