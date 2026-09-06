<?php

namespace Elemacy\Core\Constants;

defined('ABSPATH') || exit;

/**
 * Canonical WordPress post-status values used across the plugin. Use these
 * instead of bare string literals so status checks and query args stay
 * consistent and greppable.
 */
class PostStatus
{
    const PUBLISH = 'publish';
    const DRAFT   = 'draft';

    /**
     * WP_Query pseudo-status meaning "every status except trash/auto-draft".
     * Valid only as a query argument, never as a stored post status.
     */
    const ANY = 'any';
}
