<?php

namespace Elemacy\Modules\Widgets\Controllers;

defined('ABSPATH') || exit;

use Elemacy\Core\Constants\PostStatus;
use Elemacy\Core\Exceptions\ValidationException;
use Elemacy\Core\Http\SiteRequest as Request;
use Elemacy\Modules\Widgets\Widgets\LoopGrid;
use Elemacy\TemplateLibrary\Services\BlockTemplateService;
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

        $post_type = isset($settings['post_type']) ? sanitize_key((string) $settings['post_type']) : '';
        $allowed_post_types = array_merge(['current_query'], get_post_types(['public' => true], 'names'));

        if ($post_type === '' || !in_array($post_type, $allowed_post_types, true)) {
            throw new ValidationException(esc_html__('Invalid post type.', 'elemacy'));
        }

        $allowed_orderby = ['date', 'title', 'menu_order', 'rand'];
        $orderby = isset($settings['orderby']) ? sanitize_key((string) $settings['orderby']) : 'date';
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'date';
        }

        $order = isset($settings['order']) ? strtoupper(sanitize_text_field((string) $settings['order'])) : 'DESC';
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }

        $posts_per_page = isset($settings['posts_per_page']) ? (int) $settings['posts_per_page'] : 6;
        $posts_per_page = max(1, min(100, $posts_per_page));

        if ('current_query' === $post_type) {
            $current_vars = isset($settings['current_query_vars']) && is_array($settings['current_query_vars'])
                ? $settings['current_query_vars']
                : [];

            $query_args = $this->sanitize_current_query_vars($current_vars);
            $query_args['paged'] = $paged;
            $query_args['post_status'] = PostStatus::PUBLISH;
        } else {
            $query_args = [
                'post_type' => $post_type,
                'posts_per_page' => $posts_per_page,
                'orderby' => $orderby,
                'order' => $order,
                'post_status' => PostStatus::PUBLISH,
                'paged' => $paged,
            ];

            // Handling offset properly during pagination
            if (!empty($settings['offset'])) {
                $offset = (int) $settings['offset'];

                // The first page has normal offset. Subsequent pages need offset + previous pages posts
                $query_args['offset'] = $offset + (($paged - 1) * $posts_per_page);
            }

            if ('yes' === ($settings['exclude_current_post'] ?? '') && !empty($settings['current_post_id'])) {
                $query_args['post__not_in'] = [intval($settings['current_post_id'])];
            }
        }

        $query = new WP_Query($query_args);

        // Fix logic for max_num_pages when custom offset is present, so pagination numbers render correctly
        if (!empty($settings['offset']) && 'current_query' !== $post_type) {
            $offset = (int) $settings['offset'];
            $posts_per_page = isset($settings['posts_per_page']) ? (int) $settings['posts_per_page'] : 6;
            $posts_per_page = max(1, min(100, $posts_per_page));
            $total_posts = max(0, $query->found_posts - $offset);
            $query->max_num_pages = ceil($total_posts / $posts_per_page);
        }

        if (!$query->have_posts()) {
            wp_send_json_error(esc_html__('No posts found.', 'elemacy'));
        }

        $template_id = isset($settings['template_id']) ? (int) $settings['template_id'] : 0;

        // The template id round-trips through the browser, so confirm it is a real
        // library block item before handing it to Elementor to render — never an
        // arbitrary (possibly private) post id.
        if ($template_id <= 0 || !(new BlockTemplateService())->get($template_id)) {
            throw new ValidationException(esc_html__('Invalid loop template.', 'elemacy'));
        }

        ob_start();

        while ($query->have_posts()) {
            $query->the_post();

            echo '<div class="elemacy-loop-item elemacy-loop-item-' . esc_attr(get_the_ID()) . '">';
            echo Plugin::instance()->frontend->get_builder_content_for_display($template_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

    /**
     * Re-derive a safe subset of the page's main-query vars for "current query"
     * pagination. The widget round-trips the original query vars through the
     * browser, so on the way back they are attacker-controlled; rather than merge
     * them into WP_Query wholesale (which would allow injecting meta_query,
     * unbounded posts_per_page, arbitrary post__in, etc.) copy only known-safe
     * archive vars and sanitize each.
     *
     * @param array<string,mixed> $vars
     * @return array<string,mixed>
     */
    protected function sanitize_current_query_vars(array $vars): array
    {
        $safe = [];

        if (isset($vars['post_type'])) {
            $public_types = get_post_types(['public' => true], 'names');
            $requested = array_map('sanitize_key', array_map('strval', (array) $vars['post_type']));
            $allowed = array_values(array_intersect($requested, $public_types));

            if (!empty($allowed)) {
                $safe['post_type'] = count($allowed) === 1 ? $allowed[0] : $allowed;
            }
        }

        $string_keys = ['category_name', 'tag', 'author_name', 'name', 'pagename', 'taxonomy', 'term', 's'];
        foreach ($string_keys as $key) {
            if (isset($vars[$key]) && is_scalar($vars[$key])) {
                $safe[$key] = sanitize_text_field((string) $vars[$key]);
            }
        }

        $int_keys = ['cat', 'tag_id', 'author', 'year', 'monthnum', 'day', 'w', 'hour', 'minute', 'second', 'post_parent'];
        foreach ($int_keys as $key) {
            if (isset($vars[$key]) && is_numeric($vars[$key])) {
                $safe[$key] = (int) $vars[$key];
            }
        }

        if (isset($vars['orderby']) && is_scalar($vars['orderby'])) {
            $orderby = sanitize_key((string) $vars['orderby']);
            $allowed_orderby = ['date', 'title', 'menu_order', 'rand', 'id', 'name', 'modified', 'comment_count'];

            if (in_array($orderby, $allowed_orderby, true)) {
                $safe['orderby'] = $orderby;
            }
        }

        if (isset($vars['order']) && is_scalar($vars['order'])) {
            $order = strtoupper(sanitize_text_field((string) $vars['order']));
            $safe['order'] = in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';
        }

        if (isset($vars['posts_per_page']) && is_numeric($vars['posts_per_page'])) {
            $safe['posts_per_page'] = max(1, min(100, (int) $vars['posts_per_page']));
        }

        return $safe;
    }
}
