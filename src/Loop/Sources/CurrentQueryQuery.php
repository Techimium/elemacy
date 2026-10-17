<?php

namespace Elemacy\Loop\Sources;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\Items\PostLoopItem;
use WP_Query;

/**
 * The Current Query source's runtime: exactly what the page's own main query
 * returned for this request and page, kept free of any Elementor widget
 * dependency; CurrentQueryDataSource delegates get_items() to an instance of
 * this class.
 */
class CurrentQueryQuery
{
    public function get_items(array $settings): LoopResultDTO // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- The source contract passes the widget settings; the page's own query is used instead.
    {
        $query = $this->query();

        // A query that has not run (no main query yet, e.g. while Elementor
        // renders a widget to save a document) has no posts, not an error.
        $items = [];
        foreach (is_array($query->posts) ? $query->posts : [] as $post) {
            if ($post instanceof \WP_Post) {
                $items[] = new PostLoopItem($post);
            }
        }

        return new LoopResultDTO(
            $items,
            (int) $query->found_posts,
            (int) $query->max_num_pages,
            true
        );
    }

    /**
     * The request's main query, or a new one when an add-on changed its
     * args through the filter. The unfiltered path reuses the query WordPress
     * already ran, so it costs no second database query.
     *
     * @return WP_Query
     */
    protected function query(): WP_Query
    {
        global $wp_query;

        if (!$wp_query instanceof WP_Query) {
            return new WP_Query(['post__in' => [0]]);
        }

        $vars = $wp_query->query_vars;

        /**
         * Filters the main query's vars before Current Query lists them.
         *
         * @param array $vars The main query's `query_vars`.
         */
        $filtered = apply_filters(Hooks::LOOP_CURRENT_QUERY_ARGS_FILTER, $vars);

        if (!is_array($filtered) || $filtered === $vars) {
            return $wp_query;
        }

        return new WP_Query($filtered);
    }
}
