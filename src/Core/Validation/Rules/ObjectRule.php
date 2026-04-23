<?php

namespace Elemacy\Core\Validation\Rules;

defined('ABSPATH') || exit;

/**
 * Validates that a value is an object.
 *
 * @since 1.0.0
 */
class ObjectRule extends BaseRule
{
    /**
     * Check if the value is a valid object.
     *
     * @return bool
     */
    public function validate_rule()
    {
        return is_object($this->value);
    }

    /**
     * Get the error message for an invalid object value.
     *
     * @return string
     */
    public function get_error_message()
    {
        return sprintf(
            /* translators: %s: field name */
            __('The %s must be an object.', 'elemacy'),
            $this->key
        );
    }
}
