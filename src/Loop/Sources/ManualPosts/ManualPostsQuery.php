<?php

namespace Elemacy\Loop\Sources\ManualPosts;

defined('ABSPATH') || exit;

use Elemacy\Core\Constants\PostStatus;
use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\Items\PostLoopItem;
use Elemacy\Loop\Sources\Posts\PostsFilters;
use Elemacy\Loop\Sources\Posts\PostsSettings;
use Elemacy\Loop\Sources\Posts\ShownPosts;
use Elemacy\Support\PostTypes;
use WP_Query;

/**
 * Lists the posts an editor picked, in the order they were picked.
 *
 * @internal
 */
class ManualPostsQuery
{
    public function get_items(array $settings): LoopResultDTO
    {
        try {
            $ids = PostsFilters::id_list($settings[ManualPostsSettings::IDS] ?? []);
        } catch (ValidationException $error) {
            $ids = [];
        }

        if (!$ids) {
            return new LoopResultDTO([], 0, 0, true, __('Choose at least one post to show.', 'elemacy'));
        }

        $per_page = PostsSettings::page_size($settings[ManualPostsSettings::PER_PAGE] ?? null);
        $args = [
            'post_type' => PostTypes::public_names(),
            'post__in' => $ids,
            'orderby' => 'post__in',
            'post_status' => PostStatus::PUBLISH,
            'posts_per_page' => $per_page,
            'paged' => max(1, (int) ($settings['paged'] ?? 1)),
            'ignore_sticky_posts' => true,
        ];

        if (!empty($settings[ManualPostsSettings::PUBLIC_QUERY])) {
            $args['has_password'] = false;
        }

        $query = new WP_Query($args);
        $items = array_map(static fn ($post): PostLoopItem => new PostLoopItem($post), $query->posts);

        ShownPosts::add(wp_list_pluck($query->posts, 'ID'));

        return new LoopResultDTO($items, (int) $query->found_posts, (int) $query->max_num_pages, true);
    }
}
