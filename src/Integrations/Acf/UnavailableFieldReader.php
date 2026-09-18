<?php

namespace Elemacy\Integrations\Acf;

defined('ABSPATH') || exit;

use Elemacy\Integrations\Acf\Contracts\FieldReaderInterface;

/**
 * Safe null-object implementation of FieldReaderInterface: always returns
 * null, no ACF/post-meta lookup of any kind. For a caller that wants to
 * disable field reading explicitly (e.g. a loop item type with no field
 * concept at all, or a test) without writing its own conditional — the
 * "optional" half of design.md D7's "loop items consume an optional
 * field-reader contract."
 */
final class UnavailableFieldReader implements FieldReaderInterface
{
    public function get_field_value(string $key, $post_id = false)
    {
        return null;
    }
}
