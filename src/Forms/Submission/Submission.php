<?php

namespace Elemacy\Forms\Submission;

use Elemacy\Forms\Definition\Field;
use Elemacy\Forms\Definition\FormDefinition;

defined('ABSPATH') || exit;

/** A validated submission: the saved form, its values by field ID, and request facts. */
final class Submission
{
    private FormDefinition $form;

    /** @var array<string, string|string[]> */
    private array $values;
    private SubmissionMeta $meta;

    /**
     * @param FormDefinition                 $form   The saved form.
     * @param array<string, string|string[]> $values Sanitized, by field ID, in field order.
     * @param SubmissionMeta                 $meta   Request facts.
     */
    public function __construct(FormDefinition $form, array $values, SubmissionMeta $meta)
    {
        $this->form = $form;
        $this->values = $values;
        $this->meta = $meta;
    }

    public function form(): FormDefinition
    {
        return $this->form;
    }

    /** @return array<string, string|string[]> */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * @param string $field_id      A field ID.
     * @param mixed  $default_value Returned when the field submitted no value.
     * @return mixed
     */
    public function value(string $field_id, $default_value = null)
    {
        return array_key_exists($field_id, $this->values) ? $this->values[$field_id] : $default_value;
    }

    public function field(string $field_id): ?Field
    {
        return $this->form->field($field_id);
    }

    public function meta(): SubmissionMeta
    {
        return $this->meta;
    }
}
