<?php

namespace Elemacy\Forms\Rendering;

use Elemacy\Forms\Definition\Field;

defined('ABSPATH') || exit;

/**
 * Naming for one rendered form instance: DOM IDs unique on the page, the
 * request names the engine reads back, and the attributes every control
 * shares. Field types build their markup through it, so they never invent
 * their own IDs or names.
 */
final class RenderContext
{
    private string $prefix;

    public function __construct(string $prefix)
    {
        $this->prefix = $prefix;
    }

    /** The DOM ID of a field's control, or of one option of a choice field. */
    public function control_id(Field $field, ?int $option = null): string
    {
        return $this->prefix . '-' . $field->id() . (null === $option ? '' : '-' . $option);
    }

    public function error_id(Field $field): string
    {
        return $this->control_id($field) . '-error';
    }

    /** The request name the field's value is posted under. */
    public function name(Field $field, bool $multiple = false): string
    {
        return 'form_fields[' . $field->id() . ']' . ($multiple ? '[]' : '');
    }

    /**
     * The shared control attributes (id, name, class, required state, error
     * link, accessible name fallback), merged with $extra. In $extra, null,
     * false and '' drop an attribute and true writes a bare one.
     */
    public function attributes(Field $field, array $extra = []): string
    {
        $attributes = [
            'id' => $this->control_id($field),
            'name' => $this->name($field),
            'class' => 'elemacy-form-control',
            'required' => $field->is_required(),
            'aria-describedby' => $this->error_id($field),
            'aria-label' => $field->label() === '' ? ($field->text('placeholder') !== '' ? $field->text('placeholder') : $field->id()) : null,
        ];

        return self::attribute_string(array_merge($attributes, $extra));
    }

    /**
     * Escaped HTML attributes, each preceded by a space.
     *
     * @param array<string, scalar|bool|null> $attributes
     */
    public static function attribute_string(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            if (null === $value || false === $value || '' === $value) {
                continue;
            }

            $html .= true === $value ? ' ' . esc_attr($name) : ' ' . esc_attr($name) . '="' . esc_attr((string) $value) . '"';
        }

        return $html;
    }
}
