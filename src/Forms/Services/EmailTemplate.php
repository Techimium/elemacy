<?php

namespace Elemacy\Forms\Services;

use Elemacy\Forms\Contracts\FieldTypeInterface;
use Elemacy\Forms\FieldTypeRegistry;
use Elemacy\Forms\Submission\Submission;

defined('ABSPATH') || exit;

/**
 * Fills an authored HTML email body: [all-fields] lists every field that
 * submits a value, as "Label: value", and [field id="..."] inserts one field's
 * value. Values are formatted by their field type and HTML-escaped; the
 * authored body around them is trusted as written.
 */
final class EmailTemplate
{
    public const ALL_FIELDS = '[all-fields]';

    private FieldTypeRegistry $types;

    public function __construct(FieldTypeRegistry $types)
    {
        $this->types = $types;
    }

    public function render(string $body, Submission $submission): string
    {
        // One pass, so a submitted value that looks like a placeholder stays text.
        return (string) preg_replace_callback('/\[all-fields\]|\[field\s+id=(["\'])([A-Za-z0-9_-]{1,64})\1\s*\]/', function (array $found) use ($submission): string {
            return self::ALL_FIELDS === $found[0] ? $this->all_fields($submission) : nl2br(esc_html($this->text($submission, $found[2])));
        }, $body);
    }

    /** One field's value as plain text, '' when the field submitted none. */
    public function text(Submission $submission, string $field_id): string
    {
        $field = $submission->field($field_id);
        $type = $field ? $this->types->get($field->type()) : null;

        if (!$type || !array_key_exists($field_id, $submission->values())) {
            return '';
        }

        return $type->format($field, $submission->value($field_id));
    }

    private function all_fields(Submission $submission): string
    {
        $lines = [];

        foreach ($submission->form()->fields() as $id => $field) {
            $type = $this->types->get($field->type());

            if (!$type || FieldTypeInterface::LAYOUT_CONTENT === $type->layout() || !array_key_exists($id, $submission->values())) {
                continue;
            }

            $label = '' !== $field->label() ? $field->label() : $id;
            $lines[] = '<strong>' . esc_html($label) . ':</strong> ' . nl2br(esc_html($this->text($submission, $id)));
        }

        return implode('<br>', $lines);
    }
}
