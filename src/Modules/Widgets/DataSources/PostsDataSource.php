<?php

namespace Elemacy\Modules\Widgets\DataSources;

defined('ABSPATH') || exit;

use Elemacy\Core\Constants\PostStatus;
use Elemacy\Modules\Widgets\Contracts\LoopDataSourceInterface;
use Elemacy\Modules\Widgets\DTO\LoopResultDTO;
use Elemacy\Modules\Widgets\LoopItems\PostLoopItem;
use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use WP_Query;

/**
 * The built-in Posts data source: reproduces exactly what LoopGrid's and
 * LoopCarousel's Query section did before this abstraction existed (post
 * type select including "Current Query", posts-per-page, offset, order,
 * excluding the current post), so every existing site's saved widget
 * settings keep working unchanged (design.md D5).
 *
 * `current_query` mode has two callers with different needs:
 *  - A live widget render reuses the page's own already-executed main
 *    query (global $wp_query) — there is nothing else it could mean there.
 *  - AjaxPaginationController has no live main query to reuse (an
 *    admin-ajax.php request never runs the site's main query), so it
 *    sanitizes the original page's query vars itself and passes the
 *    resulting WP_Query args through the `current_query_args` settings key
 *    — see AjaxPaginationController::index().
 */
class PostsDataSource implements LoopDataSourceInterface
{
    public function get_key(): string
    {
        return 'posts';
    }

    public function get_label(): string
    {
        return esc_html__('Posts', 'elemacy');
    }

    protected function get_public_post_types(): array
    {
        $post_types = get_post_types(['public' => true], 'objects');
        $options = [
            'current_query' => esc_html__('Current Query', 'elemacy'),
        ];

        foreach ($post_types as $post_type) {
            $options[$post_type->name] = $post_type->label;
        }

        return $options;
    }

    public function register_controls(Widget_Base $widget): void
    {
        $condition = ['data_source' => $this->get_key()];

        $widget->add_control(
            'post_type',
            [
                'label' => esc_html__('Source', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_public_post_types(),
                'default' => 'post',
                'condition' => $condition,
            ]
        );

        // Loop Carousel has always called this "Posts Count"; Loop Grid
        // "Posts Per Page" — both share the same `posts_per_page` setting.
        $posts_per_page_label = 'elemacy-loop-carousel' === $widget->get_name()
            ? esc_html__('Posts Count', 'elemacy')
            : esc_html__('Posts Per Page', 'elemacy');

        $widget->add_control(
            'posts_per_page',
            [
                'label' => $posts_per_page_label,
                'type' => Controls_Manager::NUMBER,
                'default' => 6,
                'condition' => $condition + ['post_type!' => 'current_query'],
            ]
        );

        $widget->add_control(
            'offset',
            [
                'label' => esc_html__('Offset', 'elemacy'),
                'type' => Controls_Manager::NUMBER,
                'default' => 0,
                'description' => esc_html__('Number of posts to skip. Note: Using an offset can break pagination.', 'elemacy'),
                'condition' => $condition + ['post_type!' => 'current_query'],
            ]
        );

        $widget->add_control(
            'orderby',
            [
                'label' => esc_html__('Order By', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => 'date',
                'options' => [
                    'date' => esc_html__('Date', 'elemacy'),
                    'title' => esc_html__('Title', 'elemacy'),
                    'menu_order' => esc_html__('Menu Order', 'elemacy'),
                    'rand' => esc_html__('Random', 'elemacy'),
                ],
                'condition' => $condition + ['post_type!' => 'current_query'],
            ]
        );

        $widget->add_control(
            'order',
            [
                'label' => esc_html__('Order', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => 'DESC',
                'options' => [
                    'ASC' => esc_html__('ASC', 'elemacy'),
                    'DESC' => esc_html__('DESC', 'elemacy'),
                ],
                'condition' => $condition + ['post_type!' => 'current_query'],
            ]
        );

        $widget->add_control(
            'exclude_current_post',
            [
                'label' => esc_html__('Exclude Current Post', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => $condition + ['post_type!' => 'current_query'],
            ]
        );
    }

    public function get_items(array $settings): LoopResultDTO
    {
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

        return new LoopResultDTO(
            $items,
            (int) $query->found_posts,
            (int) $query->max_num_pages,
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
}
