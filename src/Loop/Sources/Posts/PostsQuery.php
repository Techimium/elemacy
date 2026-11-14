<?php

namespace Elemacy\Loop\Sources\Posts;

defined('ABSPATH') || exit;

use Elemacy\Core\Constants\PostStatus;
use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\Items\PostLoopItem;
use WP_Query;
use Elemacy\Core\Hooks;

/**
 * The Posts source's runtime query execution, kept free of any Elementor
 * widget/document dependency; PostsDataSource delegates get_items() and
 * sanitize_ajax_settings() to an instance of this class.
 *
 * @internal
 */
class PostsQuery
{
    public function get_items(array $settings): LoopResultDTO
    {
        $settings[PostsSettings::PER_PAGE] = PostsSettings::page_size($settings[PostsSettings::PER_PAGE] ?? null);
        $query = new WP_Query($this->build_query_args($settings));

        $items = [];

        foreach ($query->posts as $post) {
            $items[] = new PostLoopItem($post);
        }

        ShownPosts::add(wp_list_pluck($query->posts, 'ID'));

        $max_num_pages = (int) $query->max_num_pages;

        // WP_Query counts max_num_pages from found_posts alone, which
        // includes the posts an offset skips. The original offset is
        // `PostsSettings::OFFSET_BASE` once sanitize_ajax_settings() below has
        // compounded it for a later page, and the saved one in the editor.
        $offset = abs((int) ($settings[PostsSettings::OFFSET_BASE] ?? $settings[PostsSettings::OFFSET] ?? 0));
        $posts_per_page = (int) $query->get('posts_per_page');
        if ($offset > 0 && $posts_per_page > 0) {
            $remaining = max(0, (int) $query->found_posts - $offset);
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
     * `exclude_current_post` has two valid sources depending on the caller:
     *  - A live widget render has no `current_post_id` settings key (it is not
     *    a registered control), so it falls back to `is_singular()` /
     *    `get_the_ID()`.
     *  - AjaxPaginationController has no live "queried object" to read (an
     *    admin-ajax.php request runs outside the site's main query), so it
     *    passes it explicitly, reconstructed from its own sanitized request
     *    input.
     * `paged` is always the page the caller asked for, never the main query's.
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
            $paged = $settings['paged'] ?? 1;
            $args['paged'] = $paged;
        }

        $args = PostsFilters::apply($args, $settings);

        if (PostsFilters::avoids_duplicates($settings) && ShownPosts::all()) {
            $args['post__not_in'] = array_merge($args['post__not_in'] ?? [], ShownPosts::all());
        }

        return $this->filtered_args($args, $settings);
    }

    /**
     * Lets a site adjust this query by its Query ID. A public query keeps what
     * makes it public and its size bound, whatever the filter returned.
     */
    private function filtered_args(array $args, array $settings): array
    {
        $filtered = apply_filters(Hooks::LOOP_POSTS_QUERY_ARGS_FILTER, $args, (string) ($settings[PostsSettings::QUERY_ID] ?? ''), $settings);

        if (!is_array($filtered)) {
            return $args;
        }

        if (!empty($settings[PostsSettings::PUBLIC_QUERY])) {
            $filtered['post_status'] = PostStatus::PUBLISH;
            $filtered['has_password'] = false;
            $filtered['posts_per_page'] = $args['posts_per_page'];
        }

        return $filtered;
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

        $allowed_orderby = PostsSettings::ORDER_BY_CHOICES;
        $orderby = isset($raw_settings[PostsSettings::ORDER_BY]) ? sanitize_key((string) $raw_settings[PostsSettings::ORDER_BY]) : PostsSettings::DEFAULT_ORDER_BY;
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = PostsSettings::DEFAULT_ORDER_BY;
        }

        $order = isset($raw_settings[PostsSettings::ORDER]) ? strtoupper(sanitize_text_field((string) $raw_settings[PostsSettings::ORDER])) : PostsSettings::DEFAULT_ORDER;
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = PostsSettings::DEFAULT_ORDER;
        }

        $posts_per_page = PostsSettings::page_size($raw_settings[PostsSettings::PER_PAGE] ?? null);

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
        // max_num_pages (see get_items() above). WordPress reads a negative
        // offset as positive (absint), so a later page does too.
        $offset = abs((int) ($raw_settings[PostsSettings::OFFSET] ?? 0));
        if ($offset > 0) {
            $item_settings[PostsSettings::OFFSET_BASE] = $offset;
            $item_settings[PostsSettings::OFFSET] = $offset + (($paged - 1) * $posts_per_page);
        }

        return $item_settings + PostsFilters::sanitize($raw_settings);
    }
}
