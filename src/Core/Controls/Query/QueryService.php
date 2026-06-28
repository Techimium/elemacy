<?php

namespace Elemacy\Core\Controls\Query;

defined('ABSPATH') || exit;

use Elemacy\Support\PostTypes;
use Elemacy\Core\Constants\PostStatus;
use Elemacy\Core\DTO\QueryResultDTO;
use WP_Post;

/**
 * Resolves the live-search requests behind the elemacy_query control: searching for
 * options as the user types, and looking up the labels of already-selected values
 * when the control re-renders. Object-type aware so it can grow beyond posts.
 */
class QueryService
{
    const OBJECT_POST = 'post';

    const MAX_RESULTS = 30;

    /**
     * @param array<string, mixed> $autocomplete The control's autocomplete config.
     *
     * @return QueryResultDTO[]
     */
    public function search(array $autocomplete, string $term): array
    {
        $term = trim($term);

        if ('' === $term || self::OBJECT_POST !== ($autocomplete['object'] ?? '')) {
            return [];
        }

        $query = isset($autocomplete['query']) && is_array($autocomplete['query']) ? $autocomplete['query'] : [];

        $posts = get_posts([
            'post_type'        => $this->resolve_post_types($query),
            'post_status'      => $this->resolve_post_status($query),
            's'                => $term,
            'posts_per_page'   => self::MAX_RESULTS,
            'orderby'          => 'date',
            'order'            => 'DESC',
            'no_found_rows'    => true,
            'suppress_filters' => false,
        ]);

        return array_map(fn (WP_Post $post): QueryResultDTO => $this->post_result($post), $posts);
    }

    /**
     * Labels for already-selected ids, keyed by id, so the control can show its
     * stored value without that option being in a pre-rendered list.
     *
     * @param array<string, mixed> $get_titles The control's autocomplete config.
     * @param int[]                $ids
     *
     * @return array<int, string>
     */
    public function titles(array $get_titles, array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if (empty($ids) || self::OBJECT_POST !== ($get_titles['object'] ?? '')) {
            return [];
        }

        $query = isset($get_titles['query']) && is_array($get_titles['query']) ? $get_titles['query'] : [];

        $posts = get_posts([
            'post_type'        => $this->resolve_post_types($query),
            'post_status'      => PostStatus::ANY,
            'post__in'         => $ids,
            'posts_per_page'   => count($ids),
            'orderby'          => 'post__in',
            'no_found_rows'    => true,
            'suppress_filters' => false,
        ]);

        $titles = [];

        foreach ($posts as $post) {
            $titles[$post->ID] = $this->post_result($post)->text;
        }

        return $titles;
    }

    private function post_result(WP_Post $post): QueryResultDTO
    {
        $post_type  = get_post_type_object($post->post_type);
        $type_label = $post_type ? $post_type->labels->singular_name : $post->post_type;
        $title      = '' !== $post->post_title ? $post->post_title : __('(no title)', 'elemacy');

        $dto       = new QueryResultDTO();
        $dto->id   = (int) $post->ID;
        /* translators: 1: post title, 2: post type singular name. */
        $dto->text = sprintf(__('%1$s (%2$s)', 'elemacy'), $title, $type_label);

        return $dto;
    }

    /**
     * Post types to search, intersected with the public types so a crafted request
     * can't enumerate private/internal post types.
     *
     * @param array<string, mixed> $query
     *
     * @return string[]
     */
    private function resolve_post_types(array $query): array
    {
        $allowed = PostTypes::public_names();

        if (empty($allowed)) {
            $allowed = [self::OBJECT_POST];
        }

        $requested = array_filter(array_map('sanitize_key', (array) ($query['post_type'] ?? [])));

        if (empty($requested)) {
            return $allowed;
        }

        $valid = array_values(array_intersect($requested, $allowed));

        return empty($valid) ? $allowed : $valid;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return string[]
     */
    private function resolve_post_status(array $query): array
    {
        $status = array_filter(array_map('sanitize_key', (array) ($query['post_status'] ?? PostStatus::PUBLISH)));

        return empty($status) ? [PostStatus::PUBLISH] : $status;
    }
}
