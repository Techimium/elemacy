<?php

namespace Elemacy\Forms\Rendering;

use Elemacy\Forms\Contracts\FieldTypeInterface;
use Elemacy\Forms\Definition\Field;
use Elemacy\Forms\Definition\FormDefinition;
use Elemacy\Forms\FieldTypeRegistry;
use Elemacy\Forms\Http\SubmissionController;

defined('ABSPATH') || exit;

/**
 * The markup of a saved form, whichever builder authored it: the form
 * element, the request fields the engine reads back, the honeypot, each
 * field's wrapper, label (or legend) and error slot, the submit button and
 * the message area. Field types render only their control.
 *
 * The markup holds nothing that expires or differs per visitor, so cached
 * pages keep working. DOM IDs are unique per rendered instance.
 */
final class FormRenderer
{
    private const COLUMNS = ['20', '25', '33', '40', '50', '60', '66', '75', '80', '100'];

    /** @var array<string, int> How often each form was rendered in this request. */
    private static array $rendered = [];

    private FieldTypeRegistry $types;

    public function __construct(FieldTypeRegistry $types)
    {
        $this->types = $types;
    }

    /** Forgets the per-request instance counts (RequestState). */
    public static function reset(): void
    {
        self::$rendered = [];
    }

    /**
     * @param FormDefinition $form The saved form.
     * @param array          $view show_labels (bool, default true), button_text (string),
     *                             button_column (one of the column widths, default '100').
     */
    public function render(FormDefinition $form, array $view = []): string
    {
        $context = new RenderContext($this->prefix($form));
        $show_labels = (bool) ($view['show_labels'] ?? true);
        $button_text = (string) ($view['button_text'] ?? __('Send', 'elemacy'));

        $html = '<form class="elemacy-form" method="post" data-elemacy-form>';
        $html .= self::hidden(SubmissionController::ACTION_FIELD, SubmissionController::ACTION)
            . self::hidden(SubmissionController::SOURCE_FIELD, $form->source())
            . self::hidden(SubmissionController::OWNER_FIELD, (string) $form->owner())
            . self::hidden(SubmissionController::FORM_FIELD, $form->id());
        $html .= $this->honeypot($context);

        foreach ($form->fields() as $field) {
            $type = $this->types->get($field->type());

            // A type whose add-on is inactive renders nothing and is not validated.
            if (null !== $type) {
                $html .= $this->field($type, $field, $context, $show_labels);
            }
        }

        $html .= '<div class="elemacy-form-field elemacy-form-field-submit elemacy-form-column-' . esc_attr(self::column($view['button_column'] ?? '100')) . '">'
            . '<button type="submit" class="elemacy-form-button"><span class="elemacy-form-button-text">' . esc_html($button_text) . '</span></button>'
            . '</div>';
        $html .= '<div class="elemacy-form-messages elemacy-form-field elemacy-form-column-100" role="status" aria-live="polite"></div>';

        return $html . '</form>';
    }

    private function field(FieldTypeInterface $type, Field $field, RenderContext $context, bool $show_labels): string
    {
        $control = $type->render_control($field, $context);
        $layout = $type->layout();

        if (FieldTypeInterface::LAYOUT_HIDDEN === $layout) {
            return $control;
        }

        $classes = ['elemacy-form-field', 'elemacy-form-field-' . sanitize_html_class($type->key()), 'elemacy-form-column-' . self::column($field->setting('width', '100'))];

        if ($field->is_required()) {
            $classes[] = 'elemacy-form-field-required';
        }

        $html = '<div class="' . esc_attr(implode(' ', $classes)) . '" data-field-id="' . esc_attr($field->id()) . '">';

        if (FieldTypeInterface::LAYOUT_CONTENT === $layout) {
            return $html . $control . '</div>';
        }

        // Hidden labels stay readable by assistive technology.
        $label_class = 'elemacy-form-label' . ($show_labels ? '' : ' elemacy-screen-reader-text');

        if (FieldTypeInterface::LAYOUT_GROUP === $layout) {
            $html .= '<fieldset class="elemacy-form-group"' . RenderContext::attribute_string(['data-required' => $field->is_required() ? 'true' : null]) . '>';
            $html .= '' !== $field->label() ? '<legend class="' . esc_attr($label_class) . '">' . esc_html($field->label()) . '</legend>' : '';
            $html .= $control . '</fieldset>';
        } else {
            $html .= '' !== $field->label() ? '<label class="' . esc_attr($label_class) . '" for="' . esc_attr($context->control_id($field)) . '">' . esc_html($field->label()) . '</label>' : '';
            $html .= $control;
        }

        return $html . '<span class="elemacy-form-error" id="' . esc_attr($context->error_id($field)) . '" role="alert" hidden></span></div>';
    }

    private function honeypot(RenderContext $context): string
    {
        $id = $context->control_id(new Field('hp', 'text', '', []));

        return '<div class="elemacy-form-hp" aria-hidden="true" style="display:none !important">'
            . '<label for="' . esc_attr($id) . '">' . esc_html__('Leave this field empty', 'elemacy') . '</label>'
            . '<input type="text" id="' . esc_attr($id) . '" name="' . esc_attr(SubmissionController::HONEYPOT_FIELD) . '" value="" tabindex="-1" autocomplete="off">'
            . '</div>';
    }

    /** The DOM ID prefix: elemacy-form-{owner}-{form}, plus -{n} from the second instance in a request on. */
    private function prefix(FormDefinition $form): string
    {
        $base = 'elemacy-form-' . $form->owner() . '-' . sanitize_html_class($form->id());
        self::$rendered[$base] = (self::$rendered[$base] ?? 0) + 1;

        return self::$rendered[$base] > 1 ? $base . '-' . self::$rendered[$base] : $base;
    }

    /** @param mixed $value */
    private static function column($value): string
    {
        $value = is_scalar($value) ? (string) $value : '';

        return in_array($value, self::COLUMNS, true) ? $value : '100';
    }

    private static function hidden(string $name, string $value): string
    {
        return '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
    }
}
