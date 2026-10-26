<?php

namespace Elemacy\Forms\Fields;

use Elemacy\Forms\Definition\Field;
use Elemacy\Forms\Rendering\RenderContext;
use Elemacy\Forms\Settings\Setting;
use Elemacy\Forms\Submission\FieldResult;

defined('ABSPATH') || exit;

/**
 * A value sent without being shown. It starts as the authored value; a
 * script may change it (for example to a campaign code), so the submitted
 * value is kept, as plain single-line text.
 */
final class Hidden extends AbstractFieldType
{
    public function key(): string
    {
        return 'hidden';
    }

    public function label(): string
    {
        return __('Hidden', 'elemacy');
    }

    public function settings(): array
    {
        return [Setting::standard('field_value')];
    }

    public function layout(): string
    {
        return self::LAYOUT_HIDDEN;
    }

    public function render_control(Field $field, RenderContext $context): string
    {
        return '<input' . RenderContext::attribute_string([
            'type' => 'hidden',
            'id' => $context->control_id($field),
            'name' => $context->name($field),
            'value' => $field->text('field_value'),
        ]) . '>';
    }

    public function validate(Field $field, $raw): FieldResult
    {
        // Never required: the visitor cannot see or fill it.
        return parent::validate(new Field($field->id(), $field->type(), $field->label(), ['required' => ''] + $field->settings()), $raw);
    }
}
