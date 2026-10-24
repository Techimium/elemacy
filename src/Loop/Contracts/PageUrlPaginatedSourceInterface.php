<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

/**
 * Marker for a loop data source whose pages are the host page's own paginated
 * URLs (its items are the page's main query), not requests the signed
 * definition endpoint replays. The endpoint therefore refuses such a source,
 * and the widget paginates it by fetching those URLs instead.
 */
interface PageUrlPaginatedSourceInterface
{
}
