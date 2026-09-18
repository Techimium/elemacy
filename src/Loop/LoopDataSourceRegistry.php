<?php

namespace Elemacy\Loop;

defined('ABSPATH') || exit;

use Elemacy\Core\Capabilities\FeatureAvailability;
use Elemacy\Core\Capabilities\RegistryIdentifierGuard;
use Elemacy\Loop\Contracts\LoopDataSourceInterface;

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
        RegistryIdentifierGuard::assert_registrable(
            'Loop data source',
            $source->get_key(),
            $this->sources[$source->get_key()] ?? null,
            $source
        );

        $this->sources[$source->get_key()] = $source;

        return $this;
    }

    /**
     * Deliberately substitutes whatever is currently registered under this
     * key, bypassing register()'s duplicate-implementation guard.
     */
    public function replace(LoopDataSourceInterface $source): self
    {
        RegistryIdentifierGuard::assert_not_reserved('Loop data source', $source->get_key());

        $this->sources[$source->get_key()] = $source;

        return $this;
    }

    public function get(string $key): ?LoopDataSourceInterface
    {
        return $this->sources[$key] ?? null;
    }

    /**
     * Whether a stored source key is still registered by any module/
     * extension.
     */
    public function get_availability(string $key): FeatureAvailability
    {
        return $this->get($key) !== null
            ? FeatureAvailability::available()
            : FeatureAvailability::unknown_identifier($key);
    }

    /**
     * @return array<string, LoopDataSourceInterface>
     */
    public function all(): array
    {
        return $this->sources;
    }
}
