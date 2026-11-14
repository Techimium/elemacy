<?php

namespace Elemacy\Loop\Pagination;

defined('ABSPATH') || exit;

use Elemacy\Loop\Access\PublicLoopLimits;
use Elemacy\Loop\Access\PublicRenderPolicy;
use Elemacy\Loop\Contracts\PageUrlPaginatedSourceInterface;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Rendering\ItemPresenterRegistry;
use Elemacy\TemplateLibrary\TypeRegistry;

/**
 * Whether a document has a loop widget that makes a page of the site's own
 * paginated URL real: a paginated Loop Grid or Post Grid, reached through the
 * site's paged URLs (not through its own page argument), whose last page is
 * at least that page. WordPress counts pages from the main query alone, so it
 * answers 404 for a page that only such a widget has.
 *
 * @internal
 */
final class PageReach
{
    /**
     * @param int $owner_id The document that saves the widgets: a Theme Builder template or the viewed post.
     * @param int $page     The page of the requested URL.
     * @return bool
     */
    public static function includes(int $owner_id, int $page): bool
    {
        if ($page < 2) {
            return false;
        }

        foreach (PaginatedWidgets::in_document($owner_id) as $widget) {
            $settings = $widget['settings'];

            if (PaginatedWidgets::uses_own_argument($owner_id, $settings)) {
                continue;
            }

            $source = LoopDataSourceRegistry::instance()->get((string) ($settings['data_source'] ?? 'posts'));

            if (!$source || $source instanceof PageUrlPaginatedSourceInterface) {
                continue;
            }

            if ($page <= self::last_page($owner_id, $widget)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int                                              $owner_id
     * @param array{id: string, type: string, settings: array} $widget
     * @return int
     */
    private static function last_page(int $owner_id, array $widget): int
    {
        $policy = new PublicRenderPolicy(TypeRegistry::instance(), LoopDataSourceRegistry::instance());
        $presenter = ItemPresenterRegistry::instance()->for_widget($widget['type']);

        if (null === $presenter) {
            return 0;
        }

        try {
            $result = $policy->eligible_items($presenter, $widget['settings'], $owner_id, 1);
        } catch (\Exception $error) {
            return 0;
        }

        return PublicLoopLimits::effective_max_pages($widget['settings'], $result->max_num_pages);
    }
}
