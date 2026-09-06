<?php

namespace Elemacy\Modules\Widgets\Services;

defined('ABSPATH') || exit;

use Elemacy\Modules\Widgets\Contracts\LoopDataSourceInterface;

/**
 * In-memory registry of the loop data sources modules have declared.
 * Populated during the Widgets module's init and via
 * Hooks::LOOP_DATA_SOURCES_REGISTER_ACTION; never persisted. Mirrors
 * TemplateLibrary\TypeRegistry / ThemeBuilder's LocationRegistry.
 */
class LoopDataSourceRegistry
{
    protected static ?self $instance = null;

    /**
     * @var array<string, LoopDataSourceInterface>
     */
    protected array $sources = [];

    public static function instance(): self
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function register(LoopDataSourceInterface $source): self
    {
        $this->sources[$source->get_key()] = $source;

        return $this;
    }

    public function get(string $key): ?LoopDataSourceInterface
    {
        return $this->sources[$key] ?? null;
    }

    /**
     * @return array<string, LoopDataSourceInterface>
     */
    public function all(): array
    {
        return $this->sources;
    }
}
