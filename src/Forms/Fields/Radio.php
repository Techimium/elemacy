<?php

namespace Elemacy\Forms\Fields;

use Elemacy\Forms\Definition\Field;
use Elemacy\Forms\Rendering\RenderContext;

defined('ABSPATH') || exit;

final class Radio extends AbstractChoiceFieldType
{
    public function key(): string
    {
        return 'radio';
    }

    public function label(): string
    {
        return __('Radio', 'elemacy');
    }

    public function render_control(Field $field, RenderContext $context): string
    {
        return self::options_markup($field, $context, 'radio', false);
    }

    /**
     * One input per option. A required radio group may mark every radio
     * required (the browser then needs any one); a checkbox group must not.
     */
    public static function options_markup(Field $field, RenderContext $context, string $type, bool $multiple): string
    {
        $html = '<span class="elemacy-form-options">';

        foreach (self::choices($field) as $index => $choice) {
            $id = $context->control_id($field, $index);
            $html .= '<span class="elemacy-form-option"><input' . RenderContext::attribute_string([
                'type' => $type,
                'id' => $id,
                'name' => $context->name($field, $multiple),
                'value' => $choice['value'],
                'required' => !$multiple && $field->is_required(),
                'aria-describedby' => $context->error_id($field),
            ]) . '><label for="' . esc_attr($id) . '">' . esc_html($choice['label']) . '</label></span>';
        }

        return $html . '</span>';
    }
}
