<?php

namespace Elemacy\Forms\Services;

use Elemacy\Forms\Contracts\FieldTypeInterface;
use Elemacy\Forms\Definition\FormDefinition;
use Elemacy\Forms\FieldTypeRegistry;
use Elemacy\Http\Exceptions\ValidationException;

defined('ABSPATH') || exit;

/**
 * Checks submitted values against the saved fields: unknown field IDs are
 * rejected, each field's own type validates its value, and a field whose
 * type is no longer registered is skipped. Validating comes before
 * sanitizing, so a malformed shape or injected header cannot disappear.
 */
final class SubmissionValidator
{
    public const MAX_FIELDS = 200;
    public const MAX_BYTES  = 65536;

    private FieldTypeRegistry $types;

    public function __construct(FieldTypeRegistry $types)
    {
        $this->types = $types;
    }

    /**
     * @param FormDefinition $form   The saved form.
     * @param mixed          $values The submitted form_fields, keyed by field ID.
     * @return array<string, string|string[]> Sanitized values by field ID, in field order.
     * @throws ValidationException With errors keyed by field ID.
     */
    public function validate(FormDefinition $form, $values): array
    {
        if (null === $values) {
            $values = [];
        }

        if (!is_array($values) || count($values) > self::MAX_FIELDS || strlen((string) wp_json_encode($values)) > self::MAX_BYTES) {
            throw self::failure(['form' => __('Invalid form fields.', 'elemacy')]); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text JSON; the form runtime inserts it with textContent.
        }

        $errors = [];
        $clean = [];

        foreach (array_keys($values) as $id) {
            $field = $form->field((string) $id);
            $type = $field ? $this->types->get($field->type()) : null;

            if (!$type || FieldTypeInterface::LAYOUT_CONTENT === $type->layout()) {
                $errors[(string) $id] = __('Unknown field.', 'elemacy');
            }
        }

        foreach ($form->fields() as $id => $field) {
            $type = $this->types->get($field->type());

            if (!$type) {
                continue;
            }

            $result = $type->validate($field, $values[$id] ?? null);

            if (!$result->is_valid()) {
                $errors[$id] = $result->message();
            } elseif ($result->has_value()) {
                $clean[$id] = $result->value();
            }
        }

        if ($errors) {
            throw self::failure($errors); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text JSON; the form runtime inserts it with textContent.
        }

        return $clean;
    }

    /**
     * @param array<string, string> $errors Plain-text messages by field ID, or 'form'.
     */
    public static function failure(array $errors): ValidationException
    {
        return ValidationException::with_errors($errors, __('Please correct the highlighted fields.', 'elemacy'));
    }
}
