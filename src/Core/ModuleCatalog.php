<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

/**
 * The one place that knows how this plugin's own modules are enumerated:
 * read the configured class list, keep the classes that exist and really are
 * modules, and instantiate each one once.
 *
 * Two callers need that list at different points of a single request —
 * site seeding (which runs before the module manager exists, and at
 * activation runs outside the request lifecycle entirely) and module
 * loading. Routing both through one cached catalog is what makes reading
 * the config file with a plain `require` correct: `require_once` would hand
 * the second reader `true` instead of the array, and iterating that is a
 * runtime error rather than a visible one.
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
     * The slugs of the modules that declare themselves active by default —
     * what a site with no saved module preferences is seeded with. Returned
     * in configured order; callers treat it as a set.
     *
     * @return string[]
     */
    public function default_active_slugs(): array
    {
        $slugs = [];

        foreach ($this->all() as $module) {
            if ($module->is_default_active()) {
                $slugs[] = $module->get_name();
            }
        }

        return $slugs;
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
