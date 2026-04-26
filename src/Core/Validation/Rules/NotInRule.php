<?php

namespace Elemacy\Core\Validation\Rules;

defined('ABSPATH') || exit;

/**
 * Rule to ensure a value does not exists within a predefined set.
 *
 * @since 1.0.0
 */
class NotInRule extends BaseRule
{
    /**
     * Check if the value is in the allowed list.
     *
     * @return bool
     */
    public function validate_rule()
    {
        $not_in = $this->rule_value;

        if (is_string($not_in)) {
            $not_in = str_replace(' ', '', $not_in);
            $not_in = explode(',', $not_in);
        }

        return !in_array($this->value, $not_in, true);
    }

    /**
     * Get the error message if the value is in the not allowed list.
     *
     * @return string
     */
    public function get_error_message()
    {
        return sprintf(
            /* translators: 1: field name, 2: values */
            __('The %1$s field must not contain a value from: %2$s.', 'elemacy'),
            $this->key,
            $this->rule_value
        );
    }
}
