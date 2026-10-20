<?php

namespace Elemacy\Forms\Fields;

use Elemacy\Forms\Definition\Field;
use Elemacy\Forms\Rendering\RenderContext;
use Elemacy\Forms\Settings\Setting;
use Elemacy\Forms\Submission\FieldResult;

defined('ABSPATH') || exit;

/** Authored content shown between fields; it submits nothing. */
final class Html extends AbstractFieldType
{
    public function key(): string
    {
        return 'html';
    }

    public function label(): string
    {
        return __('HTML', 'elemacy');
    }

    public function settings(): array
    {
        return [Setting::textarea('field_html', __('HTML', 'elemacy'))];
    }

    public function layout(): string
    {
        return self::LAYOUT_CONTENT;
    }

    public function render_control(Field $field, RenderContext $context): string
    {
        $html = $field->setting('field_html', '');

        return wp_kses_post(is_string($html) ? $html : '');
    }

    public function validate(Field $field, $raw): FieldResult
    {
        return FieldResult::none();
    }

    public function format(Field $field, $value): string
    {
        return '';
    }
}
