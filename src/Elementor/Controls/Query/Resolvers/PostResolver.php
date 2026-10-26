<?php

namespace Elemacy\Elementor\Controls\Query\Resolvers;

defined('ABSPATH') || exit;

use Elemacy\Core\Constants\PostStatus;
use Elemacy\Elementor\Controls\Query\QueryResolverInterface;
use Elemacy\Http\DTO\QueryResultDTO;
use Elemacy\Support\PostTypes;
use WP_Post;

/**
 * Posts of any public type. `query` may narrow the post types and statuses.
 */
class PostResolver implements QueryResolverInterface
{
    public const KEY = 'post';

    public function key(): string
    {
        return self::KEY;
    }

    public function capability(): string
    {
        return 'edit_posts';
    }

    public function search(array $query, string $term): array
    {
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

    public function titles(array $query, array $ids): array
    {
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
            $allowed = [self::KEY];
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
