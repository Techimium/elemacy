<?php

namespace Elemacy\Forms\Definition;

defined('ABSPATH') || exit;

/**
 * A saved form as the engine sees it, whichever builder authored it: where it
 * lives, its fields in authored order, the submit actions it selected and
 * every saved setting (actions read their own from settings()).
 */
final class FormDefinition
{
    private string $source;
    private int $owner;
    private string $id;

    /** @var array<string, Field> */
    private array $fields = [];

    /** @var string[] */
    private array $actions;
    private array $settings;

    /**
     * @param string   $source   The builder's form source key.
     * @param int      $owner    The post storing the form.
     * @param string   $id       The form's ID within its owner.
     * @param Field[]  $fields   In authored order; IDs must be unique.
     * @param string[] $actions  Selected submit action keys, in authored order.
     * @param array    $settings Every saved form setting.
     */
    public function __construct(string $source, int $owner, string $id, array $fields, array $actions, array $settings)
    {
        $this->source = $source;
        $this->owner = $owner;
        $this->id = $id;

        foreach ($fields as $field) {
            $this->fields[$field->id()] = $field;
        }

        $this->actions = array_values(array_unique($actions));
        $this->settings = $settings;
    }

    public function source(): string
    {
        return $this->source;
    }

    /** The post that stores the form: a page, a post or a library template. */
    public function owner(): int
    {
        return $this->owner;
    }

    public function id(): string
    {
        return $this->id;
    }

    /** @return array<string, Field> Keyed by field ID, in authored order. */
    public function fields(): array
    {
        return $this->fields;
    }

    public function field(string $id): ?Field
    {
        return $this->fields[$id] ?? null;
    }

    /** @return string[] */
    public function actions(): array
    {
        return $this->actions;
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

    public function settings(): array
    {
        return $this->settings;
    }
}
