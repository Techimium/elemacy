<?php

namespace Elemacy\Forms\Definition;

use Elemacy\Forms\Settings\Setting;

defined('ABSPATH') || exit;

/** One authored field of a saved form, addressed by its stable field ID. */
final class Field
{
    private string $id;
    private string $type;
    private string $label;
    private array $settings;

    /**
     * @param string $id       The stable field ID.
     * @param string $type     The field type key.
     * @param string $label    The authored label.
     * @param array  $settings Every saved setting of the field (its repeater row).
     */
    public function __construct(string $id, string $type, string $label, array $settings)
    {
        $this->id = $id;
        $this->type = $type;
        $this->label = $label;
        $this->settings = $settings;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function label(): string
    {
        return $this->label;
    }

    /**
     * @param string $key           A saved setting key.
     * @param mixed  $default_value Returned when the setting is absent.
     * @return mixed
     */
    public function setting(string $key, $default_value = null)
    {
        return array_key_exists($key, $this->settings) ? $this->settings[$key] : $default_value;
    }

    /** A setting as a trimmed string, '' when absent or not scalar. */
    public function text(string $key): string
    {
        $value = $this->setting($key, '');

        return is_scalar($value) ? trim((string) $value) : '';
    }

    public function is_required(): bool
    {
        $value = $this->setting('required', '');

        return Setting::ON === $value || true === $value;
    }

    public function settings(): array
    {
        return $this->settings;
    }
}
