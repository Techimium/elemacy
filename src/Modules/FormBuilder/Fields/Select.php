<?php

namespace Elemacy\Modules\FormBuilder\Fields;

use Elemacy\Modules\FormBuilder\Definition\Field;
use Elemacy\Modules\FormBuilder\Rendering\RenderContext;
use Elemacy\Modules\FormBuilder\Settings\Setting;

defined('ABSPATH') || exit;

/**
 * A drop-down. A placeholder becomes an empty first choice, so a required
 * select left on it fails, in the browser and on the server.
 *
 * @internal
 */
final class Select extends AbstractChoiceFieldType
{
    public function key(): string
    {
        return 'select';
    }

    public function label(): string
    {
        return __('Select', 'elemacy');
    }

    public function settings(): array
    {
        return [Setting::standard('placeholder'), Setting::standard('required'), Setting::standard('options')];
    }

    public function layout(): string
    {
        return self::LAYOUT_FIELD;
    }

    public function render_control(Field $field, RenderContext $context): string
    {
        $html = '<select' . $context->attributes($field) . '>';
        $placeholder = $field->text('placeholder');

        if ('' !== $placeholder) {
            $html .= '<option value="">' . esc_html($placeholder) . '</option>';
        }

        foreach (self::choices($field) as $choice) {
            $html .= '<option value="' . esc_attr($choice['value']) . '">' . esc_html($choice['label']) . '</option>';
        }

        return $html . '</select>';
    }
}
