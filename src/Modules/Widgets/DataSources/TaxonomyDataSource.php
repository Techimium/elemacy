<?php

namespace Elemacy\Modules\Widgets\DataSources;

defined('ABSPATH') || exit;

use Elemacy\Core\Exceptions\ValidationException;
use Elemacy\Modules\Widgets\Contracts\LoopDataSourceInterface;
use Elemacy\Modules\Widgets\DTO\LoopResultDTO;
use Elemacy\Modules\Widgets\LoopItems\TermLoopItem;
use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use WP_Term;
use WP_Term_Query;

/**
 * Built-in Taxonomy Terms data source: loops over a chosen taxonomy's terms
 * instead of posts, so a Loop Grid/Carousel can build a "browse by category"
 * grid or similar. See openspec/changes/loop-data-source-taxonomy/design.md
 * for the term-meta resolution, no-op enter()/exit(), and pagination-total
 * decisions.
 */
class TaxonomyDataSource implements LoopDataSourceInterface
{
    public function get_key(): string
    {
        return 'terms';
    }

    public function get_label(): string
    {
        return esc_html__('Terms', 'elemacy');
    }

    protected function get_public_taxonomies(): array
    {
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        $options = [];

        foreach ($taxonomies as $taxonomy) {
            $options[$taxonomy->name] = $taxonomy->label;
        }

        return $options;
    }

    public function register_controls(Widget_Base $widget): void
    {
        $condition = ['data_source' => $this->get_key()];

        $widget->add_control(
            'taxonomy',
            [
                'label' => esc_html__('Taxonomy', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_public_taxonomies(),
                'default' => 'category',
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            'terms_number',
            [
                'label' => esc_html__('Number of Terms', 'elemacy'),
                'type' => Controls_Manager::NUMBER,
                'default' => 6,
                'description' => esc_html__('Leave empty to show all terms.', 'elemacy'),
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            'terms_orderby',
            [
                'label' => esc_html__('Order By', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => 'name',
                'options' => [
                    'name' => esc_html__('Name', 'elemacy'),
                    'count' => esc_html__('Count', 'elemacy'),
                    'term_id' => esc_html__('Term ID', 'elemacy'),
                    'slug' => esc_html__('Slug', 'elemacy'),
                ],
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            'terms_order',
            [
                'label' => esc_html__('Order', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => 'ASC',
                'options' => [
                    'ASC' => esc_html__('ASC', 'elemacy'),
                    'DESC' => esc_html__('DESC', 'elemacy'),
                ],
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            'hide_empty',
            [
                'label' => esc_html__('Hide Empty Terms', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            'exclude_current_term',
            [
                'label' => esc_html__('Exclude Current Term', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => esc_html__('When viewing a term archive, exclude that term from the results.', 'elemacy'),
                'condition' => $condition,
            ]
        );
    }

    public function get_items(array $settings): LoopResultDTO
    {
        $args = $this->build_query_args($settings);

        $terms = (new WP_Term_Query($args))->get_terms();

        $items = [];
        foreach ($terms as $term) {
            $items[] = new TermLoopItem($term);
        }

        $number = (int) ($settings['terms_number'] ?? 0);
        $total_items = count($items);
        $max_num_pages = 1;

        // WP_Term_Query has no found-rows-equivalent on the query object
        // itself (unlike WP_Query::$found_posts) — a second, cheap count
        // query is the only way to get a pagination total (design.md D4).
        // Only run it when pagination is actually meaningful.
        if ($number > 0) {
            $count_args = $args;
            unset($count_args['number'], $count_args['offset']);
            $count_args['fields'] = 'count';

            $total_items = (int) (new WP_Term_Query($count_args))->get_terms();
            $max_num_pages = (int) ceil($total_items / $number);
        }

        return new LoopResultDTO($items, $total_items, $max_num_pages, true);
    }

    protected function build_query_args(array $settings): array
    {
        $args = [
            'taxonomy' => $settings['taxonomy'] ?? 'category',
            'orderby' => $settings['terms_orderby'] ?? 'name',
            'order' => $settings['terms_order'] ?? 'ASC',
            'hide_empty' => 'yes' === ($settings['hide_empty'] ?? 'yes'),
        ];

        $number = (int) ($settings['terms_number'] ?? 0);

        if ($number > 0) {
            $args['number'] = $number;

            if (!empty($settings['pagination_type'])) {
                $paged = $settings['paged']
                    ?? (get_query_var('paged') ? get_query_var('paged') : (get_query_var('page') ? get_query_var('page') : 1));
                $args['offset'] = ((int) $paged - 1) * $number;
            }
        }

        if ('yes' === ($settings['exclude_current_term'] ?? '')) {
            if (array_key_exists('current_term_id', $settings)) {
                if (!empty($settings['current_term_id'])) {
                    $args['exclude'] = [(int) $settings['current_term_id']];
                }
            } else {
                $current_term_id = $this->get_current_queried_term_id();

                if ($current_term_id) {
                    $args['exclude'] = [$current_term_id];
                }
            }
        }

        return $args;
    }

    protected function get_current_queried_term_id(): int
    {
        if (!is_tax() && !is_category() && !is_tag()) {
            return 0;
        }

        $current_term = get_queried_object();

        return $current_term instanceof WP_Term ? $current_term->term_id : 0;
    }

    public function get_ajax_payload(array $settings): array
    {
        $payload = [
            'taxonomy' => $settings['taxonomy'] ?? 'category',
            'terms_number' => $settings['terms_number'] ?? 6,
            'terms_orderby' => $settings['terms_orderby'] ?? 'name',
            'terms_order' => $settings['terms_order'] ?? 'ASC',
            'hide_empty' => $settings['hide_empty'] ?? 'yes',
            'exclude_current_term' => $settings['exclude_current_term'] ?? '',
        ];

        if ('yes' === ($settings['exclude_current_term'] ?? '')) {
            $current_term_id = $this->get_current_queried_term_id();

            if ($current_term_id) {
                $payload['current_term_id'] = $current_term_id;
            }
        }

        return $payload;
    }

    public function sanitize_ajax_settings(array $raw_settings, int $paged): array
    {
        $taxonomy = isset($raw_settings['taxonomy']) ? sanitize_key((string) $raw_settings['taxonomy']) : '';
        $allowed_taxonomies = get_taxonomies(['public' => true], 'names');

        if ($taxonomy === '' || !in_array($taxonomy, $allowed_taxonomies, true)) {
            throw new ValidationException(esc_html__('Invalid taxonomy.', 'elemacy'));
        }

        $allowed_orderby = ['name', 'count', 'term_id', 'slug'];
        $orderby = isset($raw_settings['terms_orderby']) ? sanitize_key((string) $raw_settings['terms_orderby']) : 'name';
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'name';
        }

        $order = isset($raw_settings['terms_order']) ? strtoupper(sanitize_text_field((string) $raw_settings['terms_order'])) : 'ASC';
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        $number = isset($raw_settings['terms_number']) ? (int) $raw_settings['terms_number'] : 6;
        $number = max(0, min(100, $number));

        return [
            'taxonomy' => $taxonomy,
            'terms_number' => $number,
            'terms_orderby' => $orderby,
            'terms_order' => $order,
            'hide_empty' => 'yes' === ($raw_settings['hide_empty'] ?? '') ? 'yes' : 'no',
            'exclude_current_term' => 'yes' === ($raw_settings['exclude_current_term'] ?? '') ? 'yes' : 'no',
            'current_term_id' => !empty($raw_settings['current_term_id']) ? (int) $raw_settings['current_term_id'] : 0,
            'pagination_type' => 'ajax',
            'paged' => $paged,
        ];
    }
}
