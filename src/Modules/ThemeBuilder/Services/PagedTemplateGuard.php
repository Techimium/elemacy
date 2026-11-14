<?php

namespace Elemacy\Modules\ThemeBuilder\Services;

defined('ABSPATH') || exit;

use Elemacy\Loop\Pagination\PageReach;
use Elemacy\TemplateLibrary\TemplateResolver;

/**
 * Keeps WordPress from answering 404 for a paged URL that a Theme Builder
 * template makes real. WordPress counts pages from the main query alone, so
 * `/page/2/` of a blog home with one page of posts is a 404 even when the
 * archive template holds a paginated Posts grid with a second page. When the
 * template that applies to a paged archive, search or singular request holds a
 * loop widget that reaches the requested page, the request is handled as found;
 * a page beyond the widget's last page still is a 404.
 *
 * @internal
 */
final class PagedTemplateGuard
{
    public function register_hooks(): void
    {
        add_filter('pre_handle_404', [$this, 'allow_reachable_page'], 11, 2);
    }

    /**
     * @param bool      $handled  Whether another filter already handled the 404.
     * @param \WP_Query $wp_query The main query.
     * @return bool
     */
    public function allow_reachable_page($handled, $wp_query)
    {
        if ($handled || !$wp_query instanceof \WP_Query) {
            return $handled;
        }

        if (is_singular()) {
            $page = (int) $wp_query->get('page');
            $type = 'single';

            if ($page < 2 || empty($wp_query->post)) {
                return $handled;
            }
        } elseif (is_archive() || is_home() || is_search()) {
            $page = (int) $wp_query->get('paged');
            $type = LocationRegistry::instance()->current_type();

            // Only a paged request that found nothing is the 404 to prevent; a
            // term or author that does not exist is a 404 whatever the template holds.
            if ($page < 2 || null === $type || !empty($wp_query->posts) || $this->is_missing_archive_subject($wp_query)) {
                return $handled;
            }
        } else {
            return $handled;
        }

        // A request that ends in a 404 is resolved afresh below, whatever an earlier request of this process memoized.
        TemplateResolver::reset();
        $template = TemplateResolver::instance()->resolve($type);

        return null !== $template && PageReach::includes($template, $page) ? true : $handled;
    }

    private function is_missing_archive_subject(\WP_Query $wp_query): bool
    {
        return (is_category() || is_tag() || is_tax() || is_author()) && !$wp_query->get_queried_object();
    }
}
