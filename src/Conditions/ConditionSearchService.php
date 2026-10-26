<?php

namespace Elemacy\Conditions;

defined('ABSPATH') || exit;

use Elemacy\Core\Constants\PostStatus;

/** Runs the lookups behind a condition's `search` value input. */
class ConditionSearchService
{
    protected const LIMIT = 20;

    /**
     * @param array  $config   The condition's search config (entity + scope).
     * @param string $query    Free-text query typed in the combobox.
     * @param int[]  $includes Specific IDs to resolve (used to hydrate saved values).
     * @return array<int, array{value: string, label: string, unavailable?: bool}>
     */
    public function search(array $config, string $query, array $includes = []): array
    {
        $entity = $config['entity'] ?? '';

        switch ($entity) {
            case 'post':
                $found = $this->search_posts($config, $query, $includes);
                break;
            case 'term':
                $found = $this->search_terms($config, $query, $includes);
                break;
            case 'user':
                $found = $this->search_users($query, $includes);
                break;
            default:
                return [];
        }

        return $includes === [] ? $found : $this->with_missing($found, $includes);
    }

    /**
     * Saved values resolve whatever became of their item: one that no longer
     * exists is still returned, marked unavailable, so the editor shows it
     * for repair instead of waiting on a label that never comes.
     *
     * @param array<int, array{value: string, label: string}> $found
     * @param int[]                                           $includes
     * @return array<int, array{value: string, label: string, unavailable?: bool}>
     */
    protected function with_missing(array $found, array $includes): array
    {
        $listed = array_flip(array_column($found, 'value'));

        foreach ($includes as $id) {
            if (!isset($listed[(string) $id])) {
                $found[] = [
                    'value'       => (string) $id,
                    /* translators: %d: ID of a post, term or user that no longer exists. */
                    'label'       => sprintf(__('#%d (unavailable)', 'elemacy'), $id),
                    'unavailable' => true,
                ];
            }
        }

        return $found;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function search_posts(array $config, string $query, array $includes): array
    {
        $args = [
            'post_type'        => $config['post_type'] ?? 'any',
            // A saved value names its post whatever its status since; a
            // search offers only published content to choose from.
            'post_status'      => !empty($includes) ? array_values(array_diff(get_post_stati(), ['auto-draft'])) : PostStatus::PUBLISH,
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
