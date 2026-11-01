<?php

namespace Elemacy\Http\Contracts\Support;

defined('ABSPATH') || exit;

/**
 * @api
 */
interface ArrayableInterface
{
    /**
     * Convert the object to an array.
     *
     * @return array The array representation of the object
     * @since 1.0.0
     */
    public function to_array();
}
