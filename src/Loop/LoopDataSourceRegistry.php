<?php

namespace Elemacy\Loop;

defined('ABSPATH') || exit;

use Elemacy\Core\Capabilities\FeatureAvailability;
use Elemacy\Core\Capabilities\RegistryIdentifierGuard;
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
     * Registers a source assembled from narrow query/controls/(preview)/
     * (public-policy) collaborators (design.md D7) — one registration, same
     * store as register(), through the legacy aggregate-interface adapter
     * SourceDefinition::to_legacy_source() builds. Every existing consumer
     * of get()/all() (LoopGrid/LoopCarousel, AjaxPaginationController,
     * PublicRenderPolicy, PublishedLoopDefinitionResolver) needs no changes
     * at all to accept a source registered this way.
     */
    public function register_definition(SourceDefinition $definition): self
    {
        return $this->register($definition->to_legacy_source());
    }

    /**
     * Deliberately substitutes whatever is currently registered under this
     * key, bypassing register()'s duplicate-implementation guard. Reserved
     * for call sites that intentionally wrap an already-registered source
     * (e.g. an instrumented decorator counting invocations in a test) —
     * never for module/extension bootstrap, which must use register() so a
     * genuine identifier collision is still caught.
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
     * extension. No mock-stand-in concept exists for loop sources today
     * (an unlicensed extension simply skips registering its source), so
     * this only distinguishes a live registration from an unregistered one.
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
