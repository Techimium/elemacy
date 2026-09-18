<?php

namespace Elemacy\Loop\Pagination\Policies;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Loop\DataSources\PostsQuery;
use Elemacy\Modules\Widgets\Support\PublicLoopLimits;

/**
 * The Posts source's public-context policy (design.md D7) — extracted from
 * Modules\Widgets\DataSources\PostsDataSource so it can be exercised without
 * an Elementor widget/document instance. Behavior is unchanged from before
 * the move; PostsDataSource itself now delegates get_public_settings() to an
 * instance of this class. Depends on PostsQuery directly (constructor
 * injection, defaulting to a fresh instance) since validating a public
 * request still ends by running it through the query's own
 * sanitize_ajax_settings() — the same "prepare a public query without
 * executing it" contract every PublicLoopSourcePolicyInterface implementer
 * already had before this extraction.
 */
class PostsPolicy implements PublicLoopSourcePolicyInterface
{
    protected PostsQuery $query;

    public function __construct(?PostsQuery $query = null)
    {
        $this->query = $query ?? new PostsQuery();
    }

    /** {@inheritDoc} */
    public function get_public_settings(array $settings, array $context, int $page): array
    {
        PublicLoopLimits::assert_page($page);
        PublicLoopLimits::assert_scalars($settings, ['post_type', 'posts_per_page', 'offset', 'orderby', 'order', 'exclude_current_post']);
        $saved = $settings + [
            'post_type' => 'post',
            'posts_per_page' => 6,
            'exclude_current_post' => 'yes',
        ];
        $saved['posts_per_page'] = PublicLoopLimits::page_size($saved['posts_per_page']);
        $saved['current_post_id'] = (int) ($context['post_id'] ?? 0);
        unset($saved['current_query_vars'], $saved['current_query_args']);

        if ('current_query' === $saved['post_type']) {
            if (empty($context['query']) || !is_array($context['query'])) {
                throw PublicLoopLimits::invalid_settings();
            }
            $query = $context['query'];
            $public_types = get_post_types(['public' => true], 'names');
            $types = $query['post_type'] ?? 'post';
            if ('any' === $types) {
                $types = array_values($public_types);
            }
            foreach ((array) $types as $type) {
                if (!is_string($type) || !in_array($type, $public_types, true)) {
                    throw PublicLoopLimits::invalid_settings();
                }
            }
            if (!$types) {
                throw PublicLoopLimits::invalid_settings();
            }
            if (!empty($query['taxonomy']) && !in_array($query['taxonomy'], get_taxonomies(['public' => true], 'names'), true)) {
                throw PublicLoopLimits::invalid_settings();
            }
            $query['post_type'] = $types;
            $query['posts_per_page'] = PublicLoopLimits::page_size($query['posts_per_page'] ?? get_option('posts_per_page', 10));
            $saved['current_query_vars'] = $query;
        }
        $public = $this->query->sanitize_ajax_settings($saved, $page);
        if (isset($public['current_query_args'])) {
            $public['current_query_args']['has_password'] = false;
            $public['current_query_args']['posts_per_page'] = $saved['current_query_vars']['posts_per_page'];
            foreach (['p', 'page_id'] as $key) {
                if (!empty($saved['current_query_vars'][$key])) {
                    $id = $saved['current_query_vars'][$key];
                    $post = is_int($id) && $id > 0 ? get_post($id) : null;
                    if (!$post || 'publish' !== $post->post_status || '' !== $post->post_password || !is_post_publicly_viewable($post)) {
                        throw PublicLoopLimits::invalid_settings();
                    }
                    $public['current_query_args'][$key] = $id;
                }
            }
        }
        $public['_elemacy_public_query'] = true;
        return $public;
    }
}
