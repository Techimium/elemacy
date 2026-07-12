<?php

namespace Elemacy\Core\Validation\Rules;

defined('ABSPATH') || exit;

/**
 * Rule to ensure a value exists within a predefined set.
 *
 * @since 1.0.0
 */
class InRule extends BaseRule
{
    /**
     * Check if the value is in the allowed list.
     *
     * @return bool
     */
    public function validate_rule()
    {
        // A nullable field that was omitted (or sent null/empty) is the
        // NullableRule's call, not this rule's — only validate provided values.
        if (($this->value === null || $this->value === '')
            && in_array('nullable', (array) $this->all_applied_rules, true)
        ) {
            return true;
        }

        $in = $this->rule_value;

        if (is_string($in)) {
            $in = str_replace(' ', '', $in);
            $in = explode(',', $in);
        }

        return in_array($this->value, $in, true);
    }

    /**
     * Get the error message if the value is not in the allowed list.
     *
     * @return string
     */
    public function get_error_message()
    {
        return sprintf(
            /* translators: 1: field name, 2: allowed values */
            __('The %1$s field must contain a value from: %2$s.', 'elemacy'),
            $this->key,
            $this->rule_value
        );
    }
}
