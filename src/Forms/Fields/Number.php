<?php

namespace Elemacy\Forms\Fields;

use Elemacy\Forms\Definition\Field;
use Elemacy\Forms\Settings\Setting;
use Elemacy\Forms\Submission\FieldResult;

defined('ABSPATH') || exit;

/**
 * A number, optionally bounded and stepped. With no step any decimal is
 * allowed (the input gets step="any"), matching what the browser enforces.
 */
final class Number extends AbstractFieldType
{
    private const PATTERN = '/\A-?(\d+|\d*\.\d+)([eE][-+]?\d+)?\z/';

    public function key(): string
    {
        return 'number';
    }

    public function label(): string
    {
        return __('Number', 'elemacy');
    }

    public function settings(): array
    {
        return [
            Setting::standard('placeholder'),
            Setting::standard('required'),
            Setting::number('field_min', __('Min Value', 'elemacy')),
            Setting::number('field_max', __('Max Value', 'elemacy')),
            Setting::number('field_step', __('Step', 'elemacy'))->with_description(__('Leave empty to allow any number.', 'elemacy')),
        ];
    }

    protected function input_type(): string
    {
        return 'number';
    }

    protected function input_attributes(Field $field): array
    {
        $step = self::bound($field, 'field_step');

        return [
            'placeholder' => $field->text('placeholder'),
            'min' => self::bound($field, 'field_min'),
            'max' => self::bound($field, 'field_max'),
            'step' => '' !== $step && (float) $step > 0 ? $step : 'any',
        ];
    }

    protected function check(Field $field, string $value): FieldResult
    {
        $value = trim($value);

        if (1 !== preg_match(self::PATTERN, $value)) {
            return FieldResult::invalid(__('Enter a number.', 'elemacy'));
        }

        $number = (float) $value;
        $min = self::bound($field, 'field_min');
        $max = self::bound($field, 'field_max');
        $step = self::bound($field, 'field_step');

        if ('' !== $min && $number < (float) $min) {
            /* translators: %s: the smallest allowed number. */
            return FieldResult::invalid(sprintf(__('Enter a number of at least %s.', 'elemacy'), $min));
        }

        if ('' !== $max && $number > (float) $max) {
            /* translators: %s: the largest allowed number. */
            return FieldResult::invalid(sprintf(__('Enter a number of at most %s.', 'elemacy'), $max));
        }

        if ('' !== $step && (float) $step > 0) {
            $steps = ($number - ('' !== $min ? (float) $min : 0.0)) / (float) $step;

            if (abs($steps - round($steps)) > 1e-7) {
                return FieldResult::invalid(__('Enter a number that matches the allowed steps.', 'elemacy'));
            }
        }

        return FieldResult::valid($value);
    }

    /** An authored numeric setting as a string, or '' when empty or not numeric. */
    private static function bound(Field $field, string $key): string
    {
        $value = $field->text($key);

        return '' !== $value && is_numeric($value) ? $value : '';
    }
}
