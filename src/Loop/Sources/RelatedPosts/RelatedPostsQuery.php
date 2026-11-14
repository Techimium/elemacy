<?php

namespace Elemacy\Loop\Sources\RelatedPosts;

defined('ABSPATH') || exit;

use Elemacy\Core\Constants\PostStatus;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\Items\PostLoopItem;
use Elemacy\Loop\Placement\HostFacts;
use Elemacy\Loop\Sources\Posts\PostsSettings;
use Elemacy\Loop\Sources\Posts\ShownPosts;
use WP_Post;
use WP_Query;
use WP_Term;

/**
 * Lists other posts of the current post's type that share a term with it.
 *
 * @internal
 */
class RelatedPostsQuery
{
    public function get_items(array $settings): LoopResultDTO
    {
        $current = $this->current_post($settings);

        if (null === $current) {
            return new LoopResultDTO([], 0, 0, true, __('Related Posts needs a current post, so it shows nothing here.', 'elemacy'));
        }

        $query = $this->related_query($current, $settings);

        if (null === $query || 0 === (int) $query->found_posts) {
            if ('yes' !== ($settings[RelatedPostsSettings::FALLBACK] ?? RelatedPostsSettings::DEFAULT_FALLBACK)) {
                return new LoopResultDTO([], 0, 0, true);
            }
            $query = new WP_Query($this->base_args($current, $settings));
        }

        $items = array_map(static fn ($post): PostLoopItem => new PostLoopItem($post), $query->posts);

        ShownPosts::add(wp_list_pluck($query->posts, 'ID'));

        return new LoopResultDTO($items, (int) $query->found_posts, (int) $query->max_num_pages, true);
    }

    /** The current post's query, or null when it has no terms to relate by. */
    private function related_query(WP_Post $current, array $settings): ?WP_Query
    {
        $clauses = [];

        foreach ($this->taxonomies($current, $settings) as $taxonomy) {
            $terms = get_the_terms($current, $taxonomy);

            if (is_array($terms) && $terms) {
                $clauses[] = [
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => array_map(static fn (WP_Term $term): int => (int) $term->term_id, $terms),
                ];
            }
        }

        if (!$clauses) {
            return null;
        }

        $args = $this->base_args($current, $settings);
        $args['tax_query'] = array_merge(['relation' => 'OR'], $clauses); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- What this source is for.

        return new WP_Query($args);
    }

    /** @return string[] The chosen taxonomies this post has; all its public ones when none were chosen. */
    private function taxonomies(WP_Post $current, array $settings): array
    {
        $own = array_values(array_diff(
            get_object_taxonomies($current->post_type, 'names'),
            ['post_format']
        ));
        $own = array_values(array_filter($own, 'is_taxonomy_viewable'));
        $chosen = $settings[RelatedPostsSettings::TAXONOMIES] ?? [];

        if (!is_array($chosen) || !$chosen) {
            return $own;
        }

        return array_values(array_intersect($own, array_map('strval', $chosen)));
    }

    private function base_args(WP_Post $current, array $settings): array
    {
        $args = [
            'post_type' => $current->post_type,
            'post_status' => PostStatus::PUBLISH,
            'post__not_in' => [$current->ID], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- The current post must never be its own related post.
            'posts_per_page' => PostsSettings::page_size($settings[RelatedPostsSettings::PER_PAGE] ?? null),
            'orderby' => $settings[RelatedPostsSettings::ORDER_BY] ?? RelatedPostsSettings::DEFAULT_ORDER_BY,
            'order' => $settings[RelatedPostsSettings::ORDER] ?? RelatedPostsSettings::DEFAULT_ORDER,
            'paged' => max(1, (int) ($settings['paged'] ?? 1)),
            'ignore_sticky_posts' => true,
        ];

        if (!empty($settings[RelatedPostsSettings::PUBLIC_QUERY])) {
            $args['has_password'] = false;
        }

        return $args;
    }

    /** A public request has it from its verified host facts; the editor reads its own preview. */
    private function current_post(array $settings): ?WP_Post
    {
        $id = array_key_exists(RelatedPostsSettings::CURRENT_POST_ID, $settings)
            ? (int) $settings[RelatedPostsSettings::CURRENT_POST_ID]
            : (int) (HostFacts::current()[HostFacts::POST_ID] ?? get_the_ID());
        $post = $id > 0 ? get_post($id) : null;

        return $post instanceof WP_Post ? $post : null;
    }
}
