<?php

namespace Elemacy\Integrations\Acf;

defined('ABSPATH') || exit;

use Elemacy\Integrations\Acf\Contracts\FieldReaderInterface;

/**
 * Safe null-object implementation of FieldReaderInterface: always returns
 * null, no ACF/post-meta lookup of any kind.
 */
final class UnavailableFieldReader implements FieldReaderInterface
{
    public function get_field_value(string $key, $post_id = false)
    {
        return null;
    }
}
