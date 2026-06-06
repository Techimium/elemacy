<?php

namespace Elemacy\Conditions;

defined('ABSPATH') || exit;

/**
 * Runs the lookups behind a condition's `search` value input.
 *
 * Conditions declare what to search via {@see \Elemacy\Conditions\ConditionInterface::get_search_config()};
 * this service turns that config into a WordPress query and returns a flat list
 * of `[{value, label}]` options for the admin combobox.
 */
class ConditionSearchService
{
    protected const LIMIT = 20;

    /**
     * @param array    $config   The condition's search config (entity + scope).
     * @param string   $query    Free-text query typed in the combobox.
     * @param int[]    $includes Specific IDs to resolve (used to hydrate saved values).
     * @return array<int, array{value: string, label: string}>
     */
    public function search(array $config, string $query, array $includes = []): array
    {
        $entity = $config['entity'] ?? '';

        switch ($entity) {
            case 'post':
                return $this->search_posts($config, $query, $includes);
            case 'term':
                return $this->search_terms($config, $query, $includes);
            case 'user':
                return $this->search_users($query, $includes);
            default:
                return [];
        }
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function search_posts(array $config, string $query, array $includes): array
    {
        $args = [
            'post_type'        => $config['post_type'] ?? 'any',
            'post_status'      => 'publish',
            'posts_per_page'   => self::LIMIT,
            'orderby'          => 'title',
            'order'            => 'ASC',
            'suppress_filters' => false,
            'no_found_rows'    => true,
        ];

        if (!empty($includes)) {
            $args['post__in'] = $includes;
            $args['orderby']  = 'post__in';
        } elseif ($query !== '') {
            $args['s'] = $query;
        }

        $posts = get_posts($args);

        return array_map(
            static fn($post): array => [
                'value' => (string) $post->ID,
                'label' => $post->post_title !== '' ? $post->post_title : sprintf('#%d', $post->ID),
            ],
            $posts
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function search_terms(array $config, string $query, array $includes): array
    {
        $taxonomy = $config['taxonomy'] ?? '';

        if ($taxonomy === '') {
            return [];
        }

        $args = [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'number'     => self::LIMIT,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ];

        if (!empty($includes)) {
            $args['include'] = $includes;
            $args['orderby'] = 'include';
        } elseif ($query !== '') {
            $args['search'] = $query;
        }

        $terms = get_terms($args);

        if (is_wp_error($terms)) {
            return [];
        }

        return array_map(
            static fn($term): array => [
                'value' => (string) $term->term_id,
                'label' => $term->name,
            ],
            $terms
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function search_users(string $query, array $includes): array
    {
        $args = [
            'number'  => self::LIMIT,
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'fields'  => ['ID', 'display_name'],
        ];

        if (!empty($includes)) {
            $args['include'] = $includes;
        } elseif ($query !== '') {
            $args['search']         = '*' . $query . '*';
            $args['search_columns'] = ['user_login', 'user_nicename', 'display_name', 'user_email'];
        }

        $users = get_users($args);

        return array_map(
            static fn($user): array => [
                'value' => (string) $user->ID,
                'label' => $user->display_name,
            ],
            $users
        );
    }
}
