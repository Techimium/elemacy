<?php

namespace Elemacy\Core\Validation\Rules;

/**
 * Validates that the given value is a number.
 *
 * @since 1.0.0
 */
class NumberRule extends BaseRule
{
    /**
     * check for strict data type 
     * 
     * @var bool
     */
    protected $check_strict_data_type = true;

    /**
     * Determine if the value is a number.
     *
     * @return bool
     */
    public function validate_rule()
    {
        return is_numeric($this->value);
    }

    /**
     * Get the error message for a non-number value.
     *
     * @return string
     */
    public function get_error_message()
    {
        return sprintf(
            /* translators: %s: field name */
            __('The %s must be a number.', 'elemacy'),
            $this->key
        );
    }
}
