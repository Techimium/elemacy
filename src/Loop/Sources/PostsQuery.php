<?php

namespace Elemacy\Loop\Sources;

defined('ABSPATH') || exit;

use Elemacy\Core\Constants\PostStatus;
use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\Items\PostLoopItem;
use Elemacy\Loop\Pagination\PublicLoopLimits;
use WP_Query;

/**
 * The Posts source's runtime query execution, kept free of any Elementor
 * widget/document dependency; PostsDataSource delegates get_items() and
 * sanitize_ajax_settings() to an instance of this class.
 */
class PostsQuery
{
    public function get_items(array $settings): LoopResultDTO
    {
        $settings = PublicLoopLimits::bound_ajax_page_size($settings, 'posts_per_page');
        $post_type = $settings['post_type'] ?? 'post';

        if ('current_query' === $post_type) {
            if (array_key_exists('current_query_args', $settings)) {
                $query = new WP_Query($settings['current_query_args']);
            } else {
                global $wp_query;
                $query = $wp_query;
            }
        } else {
            $query = new WP_Query($this->build_query_args($settings));
        }

        $items = [];

        foreach ($query->posts as $post) {
            $items[] = new PostLoopItem($post);
        }

        $max_num_pages = (int) $query->max_num_pages;

        // Fix max_num_pages when a custom offset was in play for this AJAX
        // page: WP_Query computes it from found_posts alone, which
        // over-counts once an offset has already consumed some of those
        // posts. `offset_override_base` (the original, uncompounded offset)
        // is only ever set by sanitize_ajax_settings() below.
        if (isset($settings['offset_override_base'])) {
            $posts_per_page = max(1, (int) ($settings['posts_per_page'] ?? 1));
            $remaining = max(0, (int) $query->found_posts - (int) $settings['offset_override_base']);
            $max_num_pages = (int) ceil($remaining / $posts_per_page);
        }

        return new LoopResultDTO(
            $items,
            (int) $query->found_posts,
            $max_num_pages,
            true
        );
    }

    /**
     * `exclude_current_post` and `paged` each have two valid sources
     * depending on the caller:
     *  - A live widget render has no `current_post_id`/`paged` settings key
     *    (neither is a registered control) — falls back to `is_singular()`/
     *    `get_the_ID()` and `get_query_var('paged')`, exactly as before
     *    this abstraction existed.
     *  - AjaxPaginationController has neither a live "queried object" nor a
     *    live `paged` query var to read (an admin-ajax.php request runs
     *    outside the site's main query), so it passes both explicitly,
     *    reconstructed from its own sanitized request input.
     */
    protected function build_query_args(array $settings): array
    {
        $args = [
            'post_type' => $settings['post_type'],
            'posts_per_page' => $settings['posts_per_page'],
            'orderby' => $settings['orderby'],
            'order' => $settings['order'],
            'post_status' => PostStatus::PUBLISH,
        ];

        if (!empty($settings['_elemacy_public_query'])) {
            $args['has_password'] = false;
        }

        if (!empty($settings['offset'])) {
            $args['offset'] = $settings['offset'];
        }

        if ('yes' === $settings['exclude_current_post']) {
            if (array_key_exists('current_post_id', $settings)) {
                if (!empty($settings['current_post_id'])) {
                    $args['post__not_in'] = [(int) $settings['current_post_id']];
                }
            } elseif (is_singular()) {
                $args['post__not_in'] = [get_the_ID()];
            }
        }

        if (!empty($settings['pagination_type']) && empty($settings['offset'])) {
            $paged = $settings['paged']
                ?? (get_query_var('paged') ? get_query_var('paged') : (get_query_var('page') ? get_query_var('page') : 1));
            $args['paged'] = $paged;
        }

        return $args;
    }

    /**
     * Relocated from AjaxPaginationController (loop-data-source-taxonomy
     * ) — validation, clamping, and offset math are unchanged
     * from before the move.
     */
    public function sanitize_ajax_settings(array $raw_settings, int $paged): array
    {
        $post_type = isset($raw_settings['post_type']) ? sanitize_key((string) $raw_settings['post_type']) : '';
        $allowed_post_types = array_merge(['current_query'], get_post_types(['public' => true], 'names'));

        if ($post_type === '' || !in_array($post_type, $allowed_post_types, true)) {
            throw new ValidationException(esc_html__('Invalid post type.', 'elemacy'));
        }

        if ('current_query' === $post_type) {
            $current_vars = isset($raw_settings['current_query_vars']) && is_array($raw_settings['current_query_vars'])
                ? $raw_settings['current_query_vars']
                : [];

            $query_args = $this->sanitize_current_query_vars($current_vars);
            $query_args['paged'] = $paged;
            $query_args['post_status'] = PostStatus::PUBLISH;

            return [
                'post_type' => 'current_query',
                'current_query_args' => $query_args,
            ];
        }

        $allowed_orderby = ['date', 'title', 'menu_order', 'rand'];
        $orderby = isset($raw_settings['orderby']) ? sanitize_key((string) $raw_settings['orderby']) : 'date';
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'date';
        }

        $order = isset($raw_settings['order']) ? strtoupper(sanitize_text_field((string) $raw_settings['order'])) : 'DESC';
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }

        $posts_per_page = isset($raw_settings['posts_per_page']) ? (int) $raw_settings['posts_per_page'] : 6;
        $posts_per_page = max(1, min(100, $posts_per_page));

        $item_settings = [
            'post_type' => $post_type,
            'posts_per_page' => $posts_per_page,
            'orderby' => $orderby,
            'order' => $order,
            'paged' => $paged,
            'pagination_type' => 'ajax', // any non-empty value: gates paged/offset handling in build_query_args.
            'exclude_current_post' => 'yes' === ($raw_settings['exclude_current_post'] ?? '') ? 'yes' : 'no',
            'current_post_id' => !empty($raw_settings['current_post_id']) ? (int) $raw_settings['current_post_id'] : 0,
        ];

        // The first page has the plain offset. Subsequent pages need offset +
        // previous pages' posts; offset_override_base carries the original,
        // uncompounded offset through to get_items() so it can correct
        // max_num_pages (see get_items() above).
        if (!empty($raw_settings['offset'])) {
            $item_settings['offset_override_base'] = (int) $raw_settings['offset'];
            $item_settings['offset'] = (int) $raw_settings['offset'] + (($paged - 1) * $posts_per_page);
        }

        return $item_settings;
    }

    /**
     * Re-derive a safe subset of the page's main-query vars for "current
     * query" pagination. Relocated unchanged from AjaxPaginationController
     * (loop-data-source-taxonomy ).
     *
     * @param array<string,mixed> $vars
     * @return array<string,mixed>
     */
    protected function sanitize_current_query_vars(array $vars): array
    {
        $safe = [];

        if (isset($vars['post_type'])) {
            $public_types = get_post_types(['public' => true], 'names');
            $requested = array_map('sanitize_key', array_map('strval', (array) $vars['post_type']));
            $allowed = array_values(array_intersect($requested, $public_types));

            if (!empty($allowed)) {
                $safe['post_type'] = count($allowed) === 1 ? $allowed[0] : $allowed;
            }
        }

        $string_keys = ['category_name', 'tag', 'author_name', 'name', 'pagename', 'taxonomy', 'term', 's'];
        foreach ($string_keys as $key) {
            if (isset($vars[$key]) && is_scalar($vars[$key])) {
                $safe[$key] = sanitize_text_field((string) $vars[$key]);
            }
        }

        $int_keys = ['cat', 'tag_id', 'author', 'year', 'monthnum', 'day', 'w', 'hour', 'minute', 'second', 'post_parent'];
        foreach ($int_keys as $key) {
            if (isset($vars[$key]) && is_numeric($vars[$key])) {
                $safe[$key] = (int) $vars[$key];
            }
        }

        if (isset($vars['orderby']) && is_scalar($vars['orderby'])) {
            $orderby = sanitize_key((string) $vars['orderby']);
            $allowed_orderby = ['date', 'title', 'menu_order', 'rand', 'id', 'name', 'modified', 'comment_count'];

            if (in_array($orderby, $allowed_orderby, true)) {
                $safe['orderby'] = $orderby;
            }
        }

        if (isset($vars['order']) && is_scalar($vars['order'])) {
            $order = strtoupper(sanitize_text_field((string) $vars['order']));
            $safe['order'] = in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';
        }

        if (isset($vars['posts_per_page']) && is_numeric($vars['posts_per_page'])) {
            $safe['posts_per_page'] = max(1, min(100, (int) $vars['posts_per_page']));
        }

        return $safe;
    }
}
