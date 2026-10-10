<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

/**
 * The one place that knows how this plugin's own modules are enumerated:
 * read the configured class list, keep the classes that exist and really are
 * modules, and instantiate each one once.
 *
 * The config file is read with a plain `require`, never `require_once`:
 * a second catalog in the same request would otherwise receive `true`
 * instead of the array, and iterating that is a runtime error rather than a
 * visible one.
 */
class ModuleCatalog
{
    /**
     * An explicit class list supplied by the caller, or null to read the
     * plugin's own src/Config/modules.php.
     *
     * @var string[]|null
     */
    protected ?array $module_classes;

    /**
     * Instantiated modules, built on first request and reused after.
     *
     * @var Module[]|null
     */
    protected ?array $modules = null;

    /**
     * @param string[]|null $module_classes Fully-qualified module class names.
     *                                      Null reads the plugin's own config.
     */
    public function __construct(?array $module_classes = null)
    {
        $this->module_classes = $module_classes;
    }

    /**
     * Every module this catalog covers, in configured order. The same
     * instances on every call.
     *
     * @return Module[]
     */
    public function all(): array
    {
        if ($this->modules === null) {
            $this->modules = $this->build();
        }

        return $this->modules;
    }

    /**
     * @return Module[]
     */
    protected function build(): array
    {
        // Plain require, never require_once — see this class's own doc comment.
        $classes = $this->module_classes ?? require ELEMACY_PATH . 'src/Config/modules.php';
        $modules = [];

        foreach ((array) $classes as $module_class) {
            // Settled on the class name before constructing anything: a
            // configured entry that is missing, or exists but is not a
            // module, is skipped rather than instantiated and inspected
            // afterwards — which would fatal on any such class whose
            // constructor takes a required argument.
            if (!is_string($module_class) || !is_subclass_of($module_class, Module::class)) {
                continue;
            }

            $modules[] = new $module_class();
        }

        return $modules;
    }
}
