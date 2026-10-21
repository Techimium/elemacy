<?php

namespace Elemacy\Loop\Sources\Posts;

defined('ABSPATH') || exit;

use Elemacy\Core\Constants\PostStatus;
use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\Items\PostLoopItem;
use Elemacy\Loop\Access\PublicLoopLimits;
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
        $settings = PublicLoopLimits::bound_ajax_page_size($settings, PostsSettings::PER_PAGE);
        $query = new WP_Query($this->build_query_args($settings));

        $items = [];

        foreach ($query->posts as $post) {
            $items[] = new PostLoopItem($post);
        }

        $max_num_pages = (int) $query->max_num_pages;

        // Fix max_num_pages when a custom offset was in play for this AJAX
        // page: WP_Query computes it from found_posts alone, which
        // over-counts once an offset has already consumed some of those
        // posts. `PostsSettings::OFFSET_BASE` (the original, uncompounded offset)
        // is only ever set by sanitize_ajax_settings() below.
        if (isset($settings[PostsSettings::OFFSET_BASE])) {
            $posts_per_page = max(1, (int) ($settings[PostsSettings::PER_PAGE] ?? 1));
            $remaining = max(0, (int) $query->found_posts - (int) $settings[PostsSettings::OFFSET_BASE]);
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
            'post_type' => $settings[PostsSettings::POST_TYPE],
            'posts_per_page' => $settings[PostsSettings::PER_PAGE],
            'orderby' => $settings[PostsSettings::ORDER_BY],
            'order' => $settings[PostsSettings::ORDER],
            'post_status' => PostStatus::PUBLISH,
        ];

        if (!empty($settings[PostsSettings::PUBLIC_QUERY])) {
            $args['has_password'] = false;
        }

        if (!empty($settings[PostsSettings::OFFSET])) {
            $args['offset'] = $settings[PostsSettings::OFFSET];
        }

        if ('yes' === $settings[PostsSettings::EXCLUDE_CURRENT]) {
            if (array_key_exists(PostsSettings::CURRENT_POST_ID, $settings)) {
                if (!empty($settings[PostsSettings::CURRENT_POST_ID])) {
                    $args['post__not_in'] = [(int) $settings[PostsSettings::CURRENT_POST_ID]];
                }
            } elseif (is_singular()) {
                $args['post__not_in'] = [get_the_ID()];
            }
        }

        if (!empty($settings['pagination_type']) && empty($settings[PostsSettings::OFFSET])) {
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
        $post_type = isset($raw_settings[PostsSettings::POST_TYPE]) ? sanitize_key((string) $raw_settings[PostsSettings::POST_TYPE]) : '';
        $allowed_post_types = get_post_types(['public' => true], 'names');

        if ($post_type === '' || !in_array($post_type, $allowed_post_types, true)) {
            throw new ValidationException(esc_html__('Invalid post type.', 'elemacy'));
        }

        $allowed_orderby = ['date', 'title', 'menu_order', 'rand'];
        $orderby = isset($raw_settings[PostsSettings::ORDER_BY]) ? sanitize_key((string) $raw_settings[PostsSettings::ORDER_BY]) : PostsSettings::DEFAULT_ORDER_BY;
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = PostsSettings::DEFAULT_ORDER_BY;
        }

        $order = isset($raw_settings[PostsSettings::ORDER]) ? strtoupper(sanitize_text_field((string) $raw_settings[PostsSettings::ORDER])) : PostsSettings::DEFAULT_ORDER;
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = PostsSettings::DEFAULT_ORDER;
        }

        $posts_per_page = isset($raw_settings[PostsSettings::PER_PAGE]) ? (int) $raw_settings[PostsSettings::PER_PAGE] : PostsSettings::DEFAULT_PER_PAGE;
        $posts_per_page = max(1, min(100, $posts_per_page));

        $item_settings = [
            PostsSettings::POST_TYPE => $post_type,
            PostsSettings::PER_PAGE => $posts_per_page,
            PostsSettings::ORDER_BY => $orderby,
            PostsSettings::ORDER => $order,
            'paged' => $paged,
            'pagination_type' => 'ajax', // any non-empty value: gates paged/offset handling in build_query_args.
            PostsSettings::EXCLUDE_CURRENT => 'yes' === ($raw_settings[PostsSettings::EXCLUDE_CURRENT] ?? '') ? 'yes' : 'no',
            PostsSettings::CURRENT_POST_ID => !empty($raw_settings[PostsSettings::CURRENT_POST_ID]) ? (int) $raw_settings[PostsSettings::CURRENT_POST_ID] : 0,
        ];

        // The first page has the plain offset. Subsequent pages need offset +
        // previous pages' posts; PostsSettings::OFFSET_BASE carries the original,
        // uncompounded offset through to get_items() so it can correct
        // max_num_pages (see get_items() above).
        if (!empty($raw_settings[PostsSettings::OFFSET])) {
            $item_settings[PostsSettings::OFFSET_BASE] = (int) $raw_settings[PostsSettings::OFFSET];
            $item_settings[PostsSettings::OFFSET] = (int) $raw_settings[PostsSettings::OFFSET] + (($paged - 1) * $posts_per_page);
        }

        return $item_settings;
    }
}
