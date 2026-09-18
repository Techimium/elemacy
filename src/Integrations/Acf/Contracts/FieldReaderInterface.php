<?php

namespace Elemacy\Integrations\Acf\Contracts;

defined('ABSPATH') || exit;

/**
 * Optional field-reading capability a loop item (or anything else outside
 * the ACF integration itself) can consume without importing any concrete
 * ACF/Dynamic Tags class directly (design.md D7). Implementations decide for
 * themselves how to degrade when ACF is not active; callers never branch on
 * ACF's own presence.
 */
interface FieldReaderInterface
{
    /**
     * @param string            $key Field name (or opaque ACF post-id-shaped key, e.g. "term_5").
     * @param int|string|false  $post_id Native post ID, an ACF post-id string, or false for the current post/sub-field context.
     * @return mixed
     */
    public function get_field_value(string $key, $post_id = false);
}
