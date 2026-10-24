<?php

namespace Elemacy\Forms\Fields;

use Elemacy\Forms\Contracts\FieldTypeInterface;
use Elemacy\Forms\Definition\Field;
use Elemacy\Forms\Rendering\RenderContext;
use Elemacy\Forms\Settings\Setting;
use Elemacy\Forms\Submission\FieldResult;

defined('ABSPATH') || exit;

/**
 * A single-value text-like field: an <input> of input_type(), the required
 * rule, and rejection of oversized values, NUL bytes and (unless
 * allows_line_breaks()) line breaks, which could otherwise reach mail
 * headers. Subclasses check the trimmed, non-empty value in check().
 *
 * @api Extend this (or AbstractChoiceFieldType) for a new field type.
 */
abstract class AbstractFieldType implements FieldTypeInterface
{
    public const MAX_LENGTH = 16384;

    public function settings(): array
    {
        return [Setting::standard('placeholder'), Setting::standard('required')];
    }

    public function layout(): string
    {
        return self::LAYOUT_FIELD;
    }

    public function render_control(Field $field, RenderContext $context): string
    {
        return '<input' . $context->attributes($field, ['type' => $this->input_type()] + $this->input_attributes($field)) . '>';
    }

    public function validate(Field $field, $raw): FieldResult
    {
        if (null === $raw) {
            $raw = '';
        }

        if (!is_string($raw) || strlen($raw) > self::MAX_LENGTH || false !== strpos($raw, "\0")) {
            return FieldResult::invalid(self::invalid_message());
        }

        if (!$this->allows_line_breaks() && false !== strpbrk($raw, "\r\n")) {
            return FieldResult::invalid(self::invalid_message());
        }

        if ('' === trim($raw)) {
            return $field->is_required() ? FieldResult::invalid(self::required_message()) : FieldResult::valid('');
        }

        return $this->check($field, $raw);
    }

    public function format(Field $field, $value): string
    {
        return is_array($value) ? implode(', ', $value) : (string) $value;
    }

    protected function input_type(): string
    {
        return 'text';
    }

    /** Type-specific attributes; see RenderContext::attributes() for the rules. */
    protected function input_attributes(Field $field): array
    {
        return ['placeholder' => $field->text('placeholder')];
    }

    protected function allows_line_breaks(): bool
    {
        return false;
    }

    /** Validates a non-empty, already shape-checked value. */
    protected function check(Field $field, string $value): FieldResult
    {
        return FieldResult::valid(sanitize_text_field($value));
    }

    public static function required_message(): string
    {
        return __('This field is required.', 'elemacy');
    }

    public static function invalid_message(): string
    {
        return __('Invalid field value.', 'elemacy');
    }
}
