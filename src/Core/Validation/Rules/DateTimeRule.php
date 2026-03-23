<?php

namespace Elemacy\Core\Validation\Rules;

use Elemacy\Core\Constants\DateTimeFormats;
use DateTime;

/**
 * Validates that the given value matches db date time format.
 *
 * @since 1.0.0
 */
class DateTimeRule extends BaseRule
{
    /**
     * Determine if the value is a valid date in the given format.
     *
     * @return bool
     */
    public function validate_rule()
    {
        $date = DateTime::createFromFormat(DateTimeFormats::DB_DATETIME, $this->value);
        return $date && $date->format(DateTimeFormats::DB_DATETIME) === $this->value;
    }

    /**
     * Get the error message for invalid date format.
     *
     * @return string
     */
    public function get_error_message()
    {
        return sprintf(
            /* translators: 1: field name, 2: date format */
            __('The %1$s field must be a valid date time in the format %2$s.', 'elemacy'),
            $this->key,
            DateTimeFormats::DB_DATETIME
        );
    }
}
