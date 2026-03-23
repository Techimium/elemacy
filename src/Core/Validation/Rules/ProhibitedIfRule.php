<?php

namespace Elemacy\Core\Validation\Rules;

/**
 * Validates that a value is present and not null.
 *
 * @since 1.0.0
 */
class ProhibitedIfRule extends BaseRule
{
    /**
     * The target field name.
     *
     * @var string
     */
    protected $target_field;

    /**
     * Determine if the value is present.
     *
     * @return bool
     */
    public function validate_rule()
    {
        $field_and_value = explode(',', $this->rule_value, 2);
        $this->target_field = $field_and_value[0];
        $target_value = $field_and_value[1];

        if ($target_value === 'false' || $target_value === 'FALSE') {
            $target_value = false;
        } elseif ($target_value === 'true' || $target_value === 'TRUE') {
            $target_value = true;
        } elseif ($target_value === 'null' || $target_value === 'NULL') {
            $target_value = null;
        }

        if (array_key_exists($this->target_field, $this->data) && $this->data[$this->target_field] === $target_value) {
            return is_null($this->value) || $this->value === '';
        }

        return true;
    }

    /**
     * Get the error message for the prohibited field.
     *
     * @return string
     */
    public function get_error_message()
    {
        return sprintf(
            /* translators: 1: field name, 2: other field name, 3: other field value */
            __('The %1$s field is prohibited when %2$s is %3$s.', 'elemacy'),
            $this->key,
            $this->target_field,
            $this->rule_value
        );
    }

    protected function ignore_rule_check()
    {
        return false;
    }
}
