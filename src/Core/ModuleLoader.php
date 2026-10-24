<?php

namespace Elemacy\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Loads the request's modules: the configured ones from the catalog, then
 * whatever add-ons contribute on REGISTER_MODULES_ACTION, then runs every
 * active module.
 *
 * The ModuleManager it receives is the container-shared instance that
 * Elemacy::get_module_manager() also returns, so admin screens, REST handlers
 * and Pro all see the manager these modules were loaded into. Never bind
 * ModuleManager in a provider: providers register inside init(), and the
 * binding would discard an instance a caller may already hold, leaving the
 * request with two managers.
 */
class ModuleLoader
{
    protected ModuleManager $modules;
    protected ModuleCatalog $catalog;

    public function __construct(ModuleManager $modules, ModuleCatalog $catalog)
    {
        $this->modules = $modules;
        $this->catalog = $catalog;
    }

    public function load(): void
    {
        foreach ($this->catalog->all() as $module) {
            $this->modules->register($module);
        }

        do_action(Hooks::REGISTER_MODULES_ACTION, $this->modules);

        $this->modules->init_modules();
    }
}
