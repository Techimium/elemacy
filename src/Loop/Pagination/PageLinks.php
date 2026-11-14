<?php

namespace Elemacy\Loop\Pagination;

defined('ABSPATH') || exit;

/**
 * The `base` and `format` arguments `paginate_links()` builds a loop's page
 * links from, for the two URL shapes a widget's pages can have besides the
 * site's own `/page/N/` (which needs none): a singular page's own paged URL,
 * and a widget's own page argument.
 *
 * @internal
 */
final class PageLinks
{
    /**
     * Page links that add the widget's own page argument (PageArgument) to
     * the given URL: page one is that URL without it.
     *
     * @return array{base: string, format: string}
     */
    public static function argument(string $url, string $widget_id): array
    {
        $clean = PageArgument::url($url, $widget_id, 1);
        $fragment = '';

        $position = strpos($clean, '#');
        if (false !== $position) {
            $fragment = substr($clean, $position);
            $clean = substr($clean, 0, $position);
        }

        return [
            'base' => $clean . '%_%' . $fragment,
            'format' => (false === strpos($clean, '?') ? '?' : '&') . PageArgument::name($widget_id) . '=%#%',
        ];
    }

    /**
     * Page links of a singular post or page, the same URLs WordPress itself
     * pages its content through (`/slug/2/`, `?page=2` with plain permalinks,
     * `/page/2/` on a static front page), so the main query's `page` is what
     * the loop reads.
     *
     * @return array{base: string, format: string}
     */
    public static function singular(int $post_id): array
    {
        global $wp_rewrite;

        $permalink = (string) get_permalink($post_id);

        if (false !== strpos($permalink, '?')) {
            return [
                'base' => $permalink . '%_%',
                'format' => '&page=%#%',
            ];
        }

        $is_front_page = 'page' === get_option('show_on_front') && $post_id === (int) get_option('page_on_front');

        $format = user_trailingslashit(($is_front_page ? $wp_rewrite->pagination_base . '/' : '') . '%#%', 'single_paged');

        return [
            'base' => trailingslashit($permalink) . '%_%',
            'format' => $format,
        ];
    }
}
