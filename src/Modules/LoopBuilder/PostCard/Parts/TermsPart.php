<?php

namespace Elemacy\Modules\LoopBuilder\PostCard\Parts;

defined('ABSPATH') || exit;

use Elemacy\Conditions\Support\Taxonomies;
use Elemacy\Modules\LoopBuilder\PostCard\PostCardContext;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use WP_Post;
use WP_Term;

/**
 * The post's terms in one taxonomy.
 *
 * @internal
 */
final class TermsPart extends AbstractPostCardPart
{
    public function key(): string
    {
        return 'terms';
    }

    public function label(): string
    {
        return esc_html__('Terms', 'elemacy');
    }

    public function defaults(): array
    {
        return [
            'terms_taxonomy' => '',
            'terms_limit' => 3,
            'terms_separator' => ', ',
            'terms_link' => 'yes',
        ];
    }

    public function register_row_controls(Repeater $rows, array $condition): void
    {
        $defaults = $this->defaults();
        $taxonomies = ['' => esc_html__('Automatic', 'elemacy')];

        foreach (Taxonomies::public_objects() as $taxonomy) {
            $taxonomies[$taxonomy->name] = $taxonomy->label;
        }

        $rows->add_control(
            'terms_taxonomy',
            [
                'label' => esc_html__('Taxonomy', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => $taxonomies,
                'default' => $defaults['terms_taxonomy'],
                'description' => esc_html__('Automatic uses the first public taxonomy of each post\'s own type.', 'elemacy'),
                'condition' => $condition,
            ]
        );

        $rows->add_control(
            'terms_limit',
            [
                'label' => esc_html__('Maximum Terms', 'elemacy'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'default' => $defaults['terms_limit'],
                'condition' => $condition,
            ]
        );

        $rows->add_control(
            'terms_separator',
            [
                'label' => esc_html__('Separator', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => $defaults['terms_separator'],
                'condition' => $condition,
            ]
        );

        $rows->add_control(
            'terms_link',
            [
                'label' => esc_html__('Link to Archives', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => $defaults['terms_link'],
                'condition' => $condition,
            ]
        );
    }

    public function render(WP_Post $post, array $row, PostCardContext $card): string
    {
        $taxonomy = '' !== $row['terms_taxonomy'] ? (string) $row['terms_taxonomy'] : $this->automatic_taxonomy($post);
        $terms = '' === $taxonomy ? false : get_the_terms($post, $taxonomy);

        if (!is_array($terms) || !$terms) {
            return '';
        }

        $limit = max(1, (int) $row['terms_limit']);
        $items = [];

        foreach (array_slice($terms, 0, $limit) as $term) {
            $items[] = $this->term_markup($term, 'yes' === $row['terms_link'], $card);
        }

        return implode('<span class="elemacy-post-card__separator" aria-hidden="true">' . esc_html((string) $row['terms_separator']) . '</span>', $items);
    }

    private function automatic_taxonomy(WP_Post $post): string
    {
        foreach (get_object_taxonomies($post->post_type, 'objects') as $taxonomy) {
            if ($taxonomy->public && 'post_format' !== $taxonomy->name) {
                return $taxonomy->name;
            }
        }

        return '';
    }

    private function term_markup(WP_Term $term, bool $linked, PostCardContext $card): string
    {
        $url = $linked ? get_term_link($term) : '';

        if (!is_string($url) || '' === $url) {
            return '<span class="elemacy-post-card__term">' . esc_html($term->name) . '</span>';
        }

        return '<a class="elemacy-post-card__term" href="' . esc_url($url) . '"' . $card->link_attributes() . '>' . esc_html($term->name) . '</a>';
    }
}
