<?php

namespace Elemacy\Forms\Fields;

use Elemacy\Forms\Definition\Field;
use Elemacy\Forms\Rendering\RenderContext;
use Elemacy\Forms\Submission\FieldResult;

defined('ABSPATH') || exit;

/** Multi-line text; the only built-in type whose value may contain line breaks. */
final class Textarea extends AbstractFieldType
{
    public function key(): string
    {
        return 'textarea';
    }

    public function label(): string
    {
        return __('Textarea', 'elemacy');
    }

    public function render_control(Field $field, RenderContext $context): string
    {
        return '<textarea' . $context->attributes($field, ['rows' => 5] + $this->input_attributes($field)) . '></textarea>';
    }

    protected function allows_line_breaks(): bool
    {
        return true;
    }

    protected function check(Field $field, string $value): FieldResult
    {
        return FieldResult::valid(sanitize_textarea_field($value));
    }
}
