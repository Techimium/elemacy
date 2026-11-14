<?php

namespace Elemacy\Modules\LoopBuilder\Services;

defined('ABSPATH') || exit;

use Elemacy\Loop\Pagination\PageReach;

/**
 * Keeps WordPress from answering 404 for `/slug/2/` of a post or page whose own
 * content holds a paginated Loop Grid or Post Grid with a second page.
 * WordPress only knows the pages `<!--nextpage-->` makes, so without this a
 * widget's page link leads to a 404. A page beyond the widget's last page still is one.
 *
 * @internal
 */
final class PagedPostGuard
{
    public function register_hooks(): void
    {
        add_filter('pre_handle_404', [$this, 'allow_reachable_page'], 10, 2);
    }

    /**
     * @param bool      $handled  Whether another filter already handled the 404.
     * @param \WP_Query $wp_query The main query.
     * @return bool
     */
    public function allow_reachable_page($handled, $wp_query)
    {
        if ($handled || !$wp_query instanceof \WP_Query || !is_singular() || empty($wp_query->post)) {
            return $handled;
        }

        return PageReach::includes((int) $wp_query->post->ID, (int) $wp_query->get('page')) ? true : $handled;
    }
}
