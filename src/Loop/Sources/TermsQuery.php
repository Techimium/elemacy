<?php

namespace Elemacy\Loop\Sources;

defined('ABSPATH') || exit;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\Items\TermLoopItem;
use Elemacy\Loop\Pagination\PublicLoopLimits;
use WP_Term;
use WP_Term_Query;

/**
 * The Terms source's runtime query execution, kept free of any Elementor
 * widget/document dependency; TaxonomyDataSource delegates get_items() and
 * sanitize_ajax_settings() to an instance of this class.
 */
class TermsQuery
{
    public function get_items(array $settings): LoopResultDTO
    {
        $settings = PublicLoopLimits::bound_ajax_page_size($settings, 'terms_number');
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
        // query is the only way to get a pagination total.
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
