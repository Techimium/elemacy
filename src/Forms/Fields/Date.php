<?php

namespace Elemacy\Forms\Fields;

use Elemacy\Forms\Definition\Field;
use Elemacy\Forms\Settings\Setting;
use Elemacy\Forms\Submission\FieldResult;

defined('ABSPATH') || exit;

/** A calendar date as YYYY-MM-DD, optionally within an authored range. */
final class Date extends AbstractFieldType
{
    public function key(): string
    {
        return 'date';
    }

    public function label(): string
    {
        return __('Date', 'elemacy');
    }

    public function settings(): array
    {
        return [
            Setting::standard('required'),
            Setting::text('min_date', __('Min Date', 'elemacy'))->with_placeholder('YYYY-MM-DD'),
            Setting::text('max_date', __('Max Date', 'elemacy'))->with_placeholder('YYYY-MM-DD'),
        ];
    }

    protected function input_type(): string
    {
        return 'date';
    }

    protected function input_attributes(Field $field): array
    {
        return [
            'min' => self::bound($field, 'min_date'),
            'max' => self::bound($field, 'max_date'),
        ];
    }

    protected function check(Field $field, string $value): FieldResult
    {
        if (!self::is_date($value)) {
            return FieldResult::invalid(__('Enter a valid date.', 'elemacy'));
        }

        $min = self::bound($field, 'min_date');
        $max = self::bound($field, 'max_date');

        // ISO dates compare correctly as strings.
        if ('' !== $min && $value < $min) {
            /* translators: %s: the earliest allowed date, YYYY-MM-DD. */
            return FieldResult::invalid(sprintf(__('Choose a date on or after %s.', 'elemacy'), $min));
        }

        if ('' !== $max && $value > $max) {
            /* translators: %s: the latest allowed date, YYYY-MM-DD. */
            return FieldResult::invalid(sprintf(__('Choose a date on or before %s.', 'elemacy'), $max));
        }

        return FieldResult::valid($value);
    }

    /** An authored bound, or '' when it is empty or not a real date. */
    private static function bound(Field $field, string $key): string
    {
        $value = $field->text($key);

        return self::is_date($value) ? $value : '';
    }

    private static function is_date(string $value): bool
    {
        return 1 === preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $parts)
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }
}
