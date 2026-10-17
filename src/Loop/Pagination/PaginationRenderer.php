<?php

namespace Elemacy\Loop\Pagination;

defined('ABSPATH') || exit;

/**
 * Renders the pagination a Loop Grid emits on its classic first page and the
 * AJAX endpoint emits for later pages, from the widget's saved pagination
 * settings: page links for the link types, or a single "next page" link
 * styled as a button for Load More and Infinite Scroll.
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

        if (PaginationType::is_incremental($settings['pagination_type'] ?? '')) {
            $this->render_next_link($settings, $max_num_pages, $current_page);

            return;
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

    /**
     * The one control of Load More / Infinite Scroll: a real link to the next
     * page, so it works without scripting and for crawlers, wearing the same
     * `page-numbers` class as the link types so every Pagination style
     * control already styles it. Nothing is rendered on the last page.
     *
     * @param array $settings
     * @param int   $max_num_pages
     * @param int   $current_page
     * @return void
     */
    protected function render_next_link(array $settings, int $max_num_pages, int $current_page): void
    {
        if ($current_page >= $max_num_pages) {
            return;
        }

        $paginate_args = [
            'total' => $max_num_pages,
            'current' => $current_page,
            'prev_next' => true,
            'prev_text' => '',
            'next_text' => '',
            'type' => 'array',
        ];

        if (!empty($settings['paginate_base'])) {
            $paginate_args['base'] = $settings['paginate_base'];
        }

        $links = paginate_links($paginate_args);

        // The URL core built for the next page, already escaped by core.
        $href = '';
        foreach ((array) $links as $link) {
            if (false !== strpos($link, 'next page-numbers') && preg_match('/href="([^"]*)"/', $link, $match)) {
                $href = $match[1];
            }
        }

        if ('' === $href) {
            return;
        }

        $text = !empty($settings['load_more_text']) ? $settings['load_more_text'] : __('Load More', 'elemacy');

        echo '<nav class="elemacy-pagination" role="navigation">';
        echo '<a class="page-numbers elemacy-load-more" rel="next" href="' . $href . '">' . esc_html($text) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $href is core's own esc_url()'d paginate_links() URL.
        echo '</nav>';
    }

    /**
     * Load More / Infinite Scroll companions to the link: the end message
     * (hidden until the last page has loaded) and the live region the script
     * announces loaded items through. Printed once by the widget, outside the
     * link, so they survive the link being replaced or removed.
     *
     * @param array $settings
     * @return void
     */
    public function render_incremental_status(array $settings): void
    {
        $loading = !empty($settings['load_more_loading_text']) ? $settings['load_more_loading_text'] : __('Loading…', 'elemacy');

        printf(
            '<span class="elemacy-visually-hidden elemacy-load-more-status" role="status" aria-live="polite" data-loading-text="%s" data-loaded-text="%s"></span>',
            esc_attr($loading),
            esc_attr__('More items loaded.', 'elemacy')
        );

        if (!empty($settings['load_more_end_text'])) {
            echo '<p class="elemacy-load-more-end" hidden>' . esc_html($settings['load_more_end_text']) . '</p>';
        }
    }
}
