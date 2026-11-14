<?php

namespace Elemacy\Modules\LoopBuilder\PostCard\Parts;

defined('ABSPATH') || exit;

use Elemacy\Modules\LoopBuilder\PostCard\PostCardContext;
use Elemacy\Modules\LoopBuilder\PostCard\PostCardText;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use WP_Post;

/**
 * One fact about the post. Meta rows next to each other in a zone form one
 * line, so dragging rows is what orders the items.
 *
 * @internal
 */
final class MetaPart extends AbstractPostCardPart
{
    public function key(): string
    {
        return 'meta';
    }

    public function label(): string
    {
        return esc_html__('Meta', 'elemacy');
    }

    public function defaults(): array
    {
        return [
            'meta_item' => 'date',
            'meta_separator' => '·',
            'meta_date_format' => '',
        ];
    }

    public function register_row_controls(Repeater $rows, array $condition): void
    {
        $defaults = $this->defaults();

        $rows->add_control(
            'meta_item',
            [
                'label' => esc_html__('Show', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'author' => esc_html__('Author', 'elemacy'),
                    'date' => esc_html__('Date', 'elemacy'),
                    'time' => esc_html__('Time', 'elemacy'),
                    'modified' => esc_html__('Date Modified', 'elemacy'),
                    'comments' => esc_html__('Comments', 'elemacy'),
                    'reading_time' => esc_html__('Reading Time', 'elemacy'),
                ],
                'default' => $defaults['meta_item'],
                'description' => esc_html__('Meta rows next to each other share one line. Drag them to reorder.', 'elemacy'),
                'condition' => $condition,
            ]
        );

        $rows->add_control(
            'meta_separator',
            [
                'label' => esc_html__('Separator Before', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => $defaults['meta_separator'],
                'condition' => $condition,
            ]
        );

        $rows->add_control(
            'meta_date_format',
            [
                'label' => esc_html__('Date Format', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => $defaults['meta_date_format'],
                'placeholder' => 'F j, Y',
                'description' => esc_html__('Leave empty to use the site\'s format.', 'elemacy'),
                'condition' => $condition + ['meta_item' => ['date', 'time', 'modified']],
            ]
        );
    }

    public function groups_adjacent_rows(): bool
    {
        return true;
    }

    public function join(array $row): string
    {
        $separator = (string) $row['meta_separator'];

        return '' === $separator ? ' ' : '<span class="elemacy-post-card__separator" aria-hidden="true">' . esc_html($separator) . '</span>';
    }

    public function render(WP_Post $post, array $row, PostCardContext $card): string
    {
        $format = (string) $row['meta_date_format'];

        switch ($row['meta_item']) {
            case 'author':
                return post_type_supports($post->post_type, 'author')
                    ? esc_html((string) get_the_author_meta('display_name', (int) $post->post_author))
                    : '';
            case 'date':
                return $this->time_tag((string) get_the_date('c', $post), (string) get_the_date($format, $post));
            case 'time':
                return $this->time_tag((string) get_the_date('c', $post), (string) get_the_time($format, $post));
            case 'modified':
                return $this->time_tag((string) get_the_modified_date('c', $post), (string) get_the_modified_date($format, $post));
            case 'comments':
                return $this->comments($post);
            case 'reading_time':
                return $this->reading_time($post);
            default:
                return '';
        }
    }

    private function time_tag(string $machine, string $display): string
    {
        return '' === $display ? '' : '<time datetime="' . esc_attr($machine) . '">' . esc_html($display) . '</time>';
    }

    private function comments(WP_Post $post): string
    {
        if (!post_type_supports($post->post_type, 'comments')) {
            return '';
        }

        $count = (int) get_comments_number($post);

        /* translators: %s: number of comments */
        return esc_html(sprintf(_n('%s comment', '%s comments', $count, 'elemacy'), number_format_i18n($count)));
    }

    private function reading_time(WP_Post $post): string
    {
        $minutes = post_password_required($post) ? 0 : PostCardText::reading_minutes($post);

        /* translators: %s: minutes it takes to read the post */
        return $minutes > 0 ? esc_html(sprintf(_n('%s min read', '%s min read', $minutes, 'elemacy'), number_format_i18n($minutes))) : '';
    }
}
