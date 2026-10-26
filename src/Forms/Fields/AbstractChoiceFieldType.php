<?php

namespace Elemacy\Forms\Fields;

use Elemacy\Forms\Definition\Field;
use Elemacy\Forms\Settings\Setting;
use Elemacy\Forms\Submission\FieldResult;

defined('ABSPATH') || exit;

/**
 * A field whose value must be one (or, when is_multiple(), several) of the
 * authored options, one per line as "Label" or "Label|value".
 *
 * @api
 */
abstract class AbstractChoiceFieldType extends AbstractFieldType
{
    public function settings(): array
    {
        return [Setting::standard('required'), Setting::standard('options')];
    }

    public function layout(): string
    {
        return self::LAYOUT_GROUP;
    }

    public function validate(Field $field, $raw): FieldResult
    {
        if (!$this->is_multiple()) {
            // The shape, empty and required rules, then check() below.
            return parent::validate($field, $raw);
        }

        if (null === $raw) {
            $raw = [];
        }

        if (!is_array($raw) || array_values($raw) !== $raw || count($raw) > 200) {
            return FieldResult::invalid(self::invalid_message());
        }

        $allowed = array_column(self::choices($field), 'value');
        $values = [];

        foreach ($raw as $item) {
            if (!is_string($item) || strlen($item) > self::MAX_LENGTH) {
                return FieldResult::invalid(self::invalid_message());
            }

            if (!in_array($item, $allowed, true)) {
                return FieldResult::invalid(self::choice_message());
            }

            $values[] = $item;
        }

        $values = array_values(array_unique($values));

        if (!$values && $field->is_required()) {
            return FieldResult::invalid(self::required_message());
        }

        return FieldResult::valid(array_map('sanitize_text_field', $values));
    }

    protected function check(Field $field, string $value): FieldResult
    {
        return in_array($value, array_column(self::choices($field), 'value'), true)
            ? FieldResult::valid(sanitize_text_field($value))
            : FieldResult::invalid(self::choice_message());
    }

    protected function is_multiple(): bool
    {
        return false;
    }

    /**
     * The authored options, in order, without blank lines.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public static function choices(Field $field): array
    {
        $options = $field->setting('options', '');
        $choices = [];

        foreach (preg_split('/\r\n|\r|\n/', is_string($options) ? $options : '') as $line) {
            $line = trim($line);

            if ('' === $line) {
                continue;
            }

            $parts = explode('|', $line, 2);
            $label = trim($parts[0]);
            $value = isset($parts[1]) ? trim($parts[1]) : $label;
            $choices[] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        return $choices;
    }

    public static function choice_message(): string
    {
        return __('Choose one of the offered options.', 'elemacy');
    }
}
