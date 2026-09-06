<?php

namespace Elemacy\TemplateLibrary;

defined('ABSPATH') || exit;

/**
 * In-memory registry of the library item types modules have declared. Populated
 * during module init and via Hooks::LIBRARY_TYPES_REGISTER_ACTION; never
 * persisted.
 */
class TypeRegistry
{
    protected static ?self $instance = null;

    /**
     * @var array<string, TypeDefinition>
     */
    protected array $types = [];

    public static function instance(): self
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function register(TypeDefinition $definition): self
    {
        $this->types[$definition->name] = $definition;

        return $this;
    }

    public function get(string $name): ?TypeDefinition
    {
        return $this->types[$name] ?? null;
    }

    /**
     * @return TypeDefinition[]
     */
    public function all(): array
    {
        return array_values($this->types);
    }

    /**
     * @return TypeDefinition[]
     */
    public function by_group(string $group): array
    {
        return array_values(array_filter(
            $this->types,
            static fn (TypeDefinition $type): bool => $type->group === $group
        ));
    }

    /**
     * @return string[]
     */
    public function names_in_group(string $group): array
    {
        return array_map(
            static fn (TypeDefinition $type): string => $type->name,
            $this->by_group($group)
        );
    }
}
