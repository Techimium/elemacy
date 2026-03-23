<?php

namespace Elemacy\Modules\Widgets\Controllers;

use Elemacy\Core\Exceptions\ValidationException;
use Elemacy\Core\Http\SiteRequest as Request;
use Elemacy\Modules\Widgets\Widgets\LoopGrid;
use Elementor\Plugin;
use WP_Query;

class AjaxPaginationController
{
    public function index(Request $request)
    {
        if (empty($request->get_string('settings')) || empty($request->get_int('paged'))) {
            throw new ValidationException(esc_html__('Missing required parameters.', 'elemacy'));
        }

        $settings = json_decode($request->get_string('settings'), true);

        if (!$settings) {
            throw new ValidationException(esc_html__('Invalid settings format.', 'elemacy'));
        }

        $paged = max(1, $request->get_int('paged'));

        $query_args = [
            'post_type' => $settings['post_type'],
            'posts_per_page' => $settings['posts_per_page'],
            'orderby' => $settings['orderby'],
            'order' => $settings['order'],
            'post_status' => 'publish',
            'paged' => $paged,
        ];

        // Handling offset properly during pagination
        if (!empty($settings['offset'])) {
            $offset = intval($settings['offset']);
            $posts_per_page = intval($settings['posts_per_page']);

            // The first page has normal offset. Subsequent pages need offset + previous pages posts
            $query_args['offset'] = $offset + (($paged - 1) * $posts_per_page);
        }

        if ('yes' === $settings['exclude_current_post'] && !empty($settings['current_post_id'])) {
            $query_args['post__not_in'] = [intval($settings['current_post_id'])];
        }

        if ($settings['post_type'] === 'current_query' && !empty($settings['current_query_vars'])) {
            $query_args = array_merge($settings['current_query_vars'], ['paged' => $paged]);
        }

        $query = new WP_Query($query_args);

        // Fix logic for max_num_pages when custom offset is present, so pagination numbers render correctly
        if (!empty($settings['offset'])) {
            $offset = intval($settings['offset']);
            $posts_per_page = intval($settings['posts_per_page']);
            $total_posts = max(0, $query->found_posts - $offset);
            $query->max_num_pages = ceil($total_posts / $posts_per_page);
        }

        if (!$query->have_posts()) {
            wp_send_json_error(esc_html__('No posts found.', 'elemacy'));
        }

        ob_start();

        while ($query->have_posts()) {
            $query->the_post();

            echo '<div class="elemacy-loop-item elemacy-loop-item-' . esc_attr(get_the_ID()) . '">';
            echo Plugin::instance()->frontend->get_builder_content_for_display($settings['template_id']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</div>';
        }

        wp_reset_postdata();

        $items_html = ob_get_clean();

        // Pagination HTML
        ob_start();
        if (!empty($settings['pagination_type']) && $query->max_num_pages > 1) {
            LoopGrid::render_pagination_html($settings, $query, $paged);
        }
        $pagination_html = ob_get_clean();

        wp_send_json_success([
            'items' => $items_html,
            'pagination' => $pagination_html,
        ]);
    }
}