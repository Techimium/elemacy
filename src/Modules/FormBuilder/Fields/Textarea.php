<?php

namespace Elemacy\Modules\FormBuilder\Fields;

use Elemacy\Modules\FormBuilder\Definition\Field;
use Elemacy\Modules\FormBuilder\Rendering\RenderContext;
use Elemacy\Modules\FormBuilder\Submission\FieldResult;

defined('ABSPATH') || exit;

/**
 * Multi-line text; the only built-in type whose value may contain line breaks.
 *
 * @internal
 */
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
