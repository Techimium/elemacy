<?php

namespace Elemacy\Forms\Fields;

use Elemacy\Forms\Definition\Field;
use Elemacy\Forms\Settings\Setting;
use Elemacy\Forms\Submission\FieldResult;

defined('ABSPATH') || exit;

/** A time of day as 24-hour HH:MM, which is what the browser's time input posts. */
final class Time extends AbstractFieldType
{
    public function key(): string
    {
        return 'time';
    }

    public function label(): string
    {
        return __('Time', 'elemacy');
    }

    public function settings(): array
    {
        return [Setting::standard('required')];
    }

    protected function input_type(): string
    {
        return 'time';
    }

    protected function input_attributes(Field $field): array
    {
        return [];
    }

    protected function check(Field $field, string $value): FieldResult
    {
        return 1 === preg_match('/\A([01][0-9]|2[0-3]):[0-5][0-9]\z/', $value)
            ? FieldResult::valid($value)
            : FieldResult::invalid(__('Enter a valid time.', 'elemacy'));
    }
}
