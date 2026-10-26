<?php

namespace Elemacy\Loop\Sources\Terms;

defined('ABSPATH') || exit;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\Items\TermLoopItem;
use Elemacy\Loop\Access\PublicLoopLimits;
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
        $settings = PublicLoopLimits::bound_ajax_page_size($settings, TermsSettings::NUMBER);
        $args = $this->build_query_args($settings);

        $terms = (new WP_Term_Query($args))->get_terms();

        $items = [];
        foreach ($terms as $term) {
            $items[] = new TermLoopItem($term);
        }

        $number = (int) ($settings[TermsSettings::NUMBER] ?? 0);
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
            'taxonomy' => $settings[TermsSettings::TAXONOMY] ?? TermsSettings::DEFAULT_TAXONOMY,
            'orderby' => $settings[TermsSettings::ORDER_BY] ?? TermsSettings::DEFAULT_ORDER_BY,
            'order' => $settings[TermsSettings::ORDER] ?? TermsSettings::DEFAULT_ORDER,
            'hide_empty' => 'yes' === ($settings[TermsSettings::HIDE_EMPTY] ?? TermsSettings::DEFAULT_HIDE_EMPTY),
        ];

        $number = (int) ($settings[TermsSettings::NUMBER] ?? 0);

        if ($number > 0) {
            $args['number'] = $number;

            if (!empty($settings['pagination_type'])) {
                $paged = $settings['paged']
                    ?? (get_query_var('paged') ? get_query_var('paged') : (get_query_var('page') ? get_query_var('page') : 1));
                $args['offset'] = ((int) $paged - 1) * $number;
            }
        }

        if ('yes' === ($settings[TermsSettings::EXCLUDE_CURRENT] ?? '')) {
            if (array_key_exists(TermsSettings::CURRENT_TERM_ID, $settings)) {
                if (!empty($settings[TermsSettings::CURRENT_TERM_ID])) {
                    $args['exclude'] = [(int) $settings[TermsSettings::CURRENT_TERM_ID]];
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
        $taxonomy = isset($raw_settings[TermsSettings::TAXONOMY]) ? sanitize_key((string) $raw_settings[TermsSettings::TAXONOMY]) : '';
        $allowed_taxonomies = get_taxonomies(['public' => true], 'names');

        if ($taxonomy === '' || !in_array($taxonomy, $allowed_taxonomies, true)) {
            throw new ValidationException(esc_html__('Invalid taxonomy.', 'elemacy'));
        }

        $allowed_orderby = ['name', 'count', 'term_id', 'slug'];
        $orderby = isset($raw_settings[TermsSettings::ORDER_BY]) ? sanitize_key((string) $raw_settings[TermsSettings::ORDER_BY]) : TermsSettings::DEFAULT_ORDER_BY;
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = TermsSettings::DEFAULT_ORDER_BY;
        }

        $order = isset($raw_settings[TermsSettings::ORDER]) ? strtoupper(sanitize_text_field((string) $raw_settings[TermsSettings::ORDER])) : TermsSettings::DEFAULT_ORDER;
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = TermsSettings::DEFAULT_ORDER;
        }

        $number = isset($raw_settings[TermsSettings::NUMBER]) ? (int) $raw_settings[TermsSettings::NUMBER] : TermsSettings::DEFAULT_NUMBER;
        $number = max(0, min(100, $number));

        return [
            TermsSettings::TAXONOMY => $taxonomy,
            TermsSettings::NUMBER => $number,
            TermsSettings::ORDER_BY => $orderby,
            TermsSettings::ORDER => $order,
            TermsSettings::HIDE_EMPTY => 'yes' === ($raw_settings[TermsSettings::HIDE_EMPTY] ?? '') ? 'yes' : 'no',
            TermsSettings::EXCLUDE_CURRENT => 'yes' === ($raw_settings[TermsSettings::EXCLUDE_CURRENT] ?? '') ? 'yes' : 'no',
            TermsSettings::CURRENT_TERM_ID => !empty($raw_settings[TermsSettings::CURRENT_TERM_ID]) ? (int) $raw_settings[TermsSettings::CURRENT_TERM_ID] : 0,
            'pagination_type' => 'ajax',
            'paged' => $paged,
        ];
    }
}
