<?php

namespace Elemacy\Modules\ThemeBuilder\Services;

defined('ABSPATH') || exit;

/**
 * Maps the current request to a template type for mutually-exclusive "content"
 * locations (single, archive, search, 404, …). Replaces hardcoded is_*()
 * branches so add-ons can register their own locations (checkout, author, …).
 * Header and footer are not locations — they are resolved independently.
 */
class LocationRegistry
{
    protected static ?self $instance = null;

    /**
     * @var array<int, array{type:string, matches:callable, priority:int}>
     */
    protected array $locations = [];

    public static function instance(): self
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * @param callable $matches Returns true when the location applies to the
     *                          current request. Evaluated lazily at resolve time.
     */
    public function register(string $type, callable $matches, int $priority = 10): self
    {
        $this->locations[] = [
            'type'     => $type,
            'matches'  => $matches,
            'priority' => $priority,
        ];

        return $this;
    }

    /**
     * Template type for the first matching location (lowest priority number
     * wins, so specific locations can be registered ahead of generic ones).
     */
    public function current_type(): ?string
    {
        $locations = $this->locations;

        usort(
            $locations,
            static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']
        );

        foreach ($locations as $location) {
            if (($location['matches'])()) {
                return $location['type'];
            }
        }

        return null;
    }
}
