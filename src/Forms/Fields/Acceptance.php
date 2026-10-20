<?php

namespace Elemacy\Forms\Fields;

use Elemacy\Forms\Definition\Field;
use Elemacy\Forms\Rendering\RenderContext;
use Elemacy\Forms\Settings\Setting;
use Elemacy\Forms\Submission\FieldResult;

defined('ABSPATH') || exit;

/** A single checkbox to accept terms; required means it must be ticked. */
final class Acceptance extends AbstractFieldType
{
    public const ACCEPTED     = 'yes';
    public const NOT_ACCEPTED = 'no';

    public function key(): string
    {
        return 'acceptance';
    }

    public function label(): string
    {
        return __('Acceptance', 'elemacy');
    }

    public function settings(): array
    {
        return [
            Setting::standard('required'),
            Setting::textarea('acceptance_text', __('Acceptance Text', 'elemacy'))->with_description(__('Links and basic formatting are allowed.', 'elemacy')),
            Setting::switcher('checked_by_default', __('Checked by Default', 'elemacy')),
        ];
    }

    public function layout(): string
    {
        return self::LAYOUT_GROUP;
    }

    public function render_control(Field $field, RenderContext $context): string
    {
        $text = $field->text('acceptance_text');
        $id = $context->control_id($field);

        return '<span class="elemacy-form-option"><input' . RenderContext::attribute_string([
            'type' => 'checkbox',
            'id' => $id,
            'name' => $context->name($field),
            'value' => 'on',
            'required' => $field->is_required(),
            'checked' => Setting::ON === $field->setting('checked_by_default'),
            'aria-describedby' => $context->error_id($field),
        ]) . '><label for="' . esc_attr($id) . '">' . ('' !== $text ? wp_kses($text, self::allowed_html()) : esc_html__('I agree', 'elemacy')) . '</label></span>';
    }

    public function validate(Field $field, $raw): FieldResult
    {
        if (null === $raw || '' === $raw) {
            return $field->is_required() ? FieldResult::invalid(self::required_message()) : FieldResult::valid(self::NOT_ACCEPTED);
        }

        return 'on' === $raw ? FieldResult::valid(self::ACCEPTED) : FieldResult::invalid(self::invalid_message());
    }

    public function format(Field $field, $value): string
    {
        return self::ACCEPTED === $value ? __('Accepted', 'elemacy') : __('Not accepted', 'elemacy');
    }

    /** Inline tags only: the text sits inside a <label>. */
    private static function allowed_html(): array
    {
        $inline = ['class' => true];

        return [
            'a' => [
                'href' => true,
                'target' => true,
                'rel' => true,
                'class' => true,
            ],
            'strong' => $inline,
            'b' => $inline,
            'em' => $inline,
            'i' => $inline,
            'span' => $inline,
            'br' => [],
        ];
    }
}
