<?php

namespace Elemacy\Loop\Pagination;

defined('ABSPATH') || exit;

/**
 * Renders a loop widget's pagination markup from its saved display
 * settings (design.md D8) — moved verbatim out of
 * Modules\Widgets\Widgets\LoopGrid::render_pagination_html(), the only two
 * callers of which were that same widget's own render() and
 * AjaxPaginationController's cross-class static call. Behavior is
 * unchanged; only the ownership moved, so both callers stay in sync by
 * construction rather than by keeping two independent implementations
 * consistent by hand.
 */
class PaginationRenderer
{
    /**
     * @param array    $settings
     * @param int      $max_num_pages
     * @param int|null $current_page
     * @return void
     */
    public function render(array $settings, int $max_num_pages, ?int $current_page = null): void
    {
        if ($current_page === null) {
            $current_page = max(1, get_query_var('paged'), get_query_var('page'));
        }
        $show_all = false;
        $prev_next = false;
        $prev_text = esc_html__('« Previous', 'elemacy');
        $next_text = esc_html__('Next »', 'elemacy');
        $show_numbers = false;

        switch ($settings['pagination_type']) {
            case 'numbers':
                $show_numbers = true;
                break;
            case 'prev_next':
                $prev_next = true;
                break;
            case 'numbers_and_prev_next':
                $show_numbers = true;
                $prev_next = true;
                break;
        }

        $paginate_args = [
            'total' => $max_num_pages,
            'current' => $current_page,
            'prev_next' => $prev_next,
            'prev_text' => $prev_text,
            'next_text' => $next_text,
            'show_all' => $show_all,
            'type' => 'array',
        ];

        if (!empty($settings['paginate_base'])) {
            $paginate_args['base'] = $settings['paginate_base'];
        }

        $links = paginate_links($paginate_args);

        if (!is_array($links)) {
            return;
        }

        // paginate_links() has no way to suppress the number list, so the
        // Previous/Next-only mode keeps just the prev/next anchors.
        if (!$show_numbers) {
            $links = array_filter($links, static function ($link) {
                return strpos($link, 'prev page-numbers') !== false
                    || strpos($link, 'next page-numbers') !== false;
            });
        }

        if (!empty($links)) {
            echo '<nav class="elemacy-pagination" role="navigation">';
            echo wp_kses_post(implode("\n", $links));
            echo '</nav>';
        }
    }
}
