<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

use Elemacy\Core\Constants\OptionKeys;
use Elemacy\Core\Exceptions\ModuleNotFoundException;
use Elemacy\Core\Http\Response;

class ModuleManager
{
    protected array $modules = [];
    protected array $active_modules = [];

    /**
     * Registers a module, keyed by its slug.
     *
     * @param Module $module The module to register.
     * @return void
     */
    public function register(Module $module): void
    {
        $this->modules[$module->get_name()] = $module;
    }

    /**
     * Returns every registered module, keyed by slug.
     *
     * @return array<string, Module>
     */
    public function get_modules(): array
    {
        return $this->modules;
    }

    /**
     * Returns every registered module as a plain array, for the REST API.
     *
     * @return array<int, array{name:string,title:string,icon:string,description:string,is_active:bool,is_headless:bool,is_mock:bool,badge:?string,url:?string}>
     */
    public function to_array(): array
    {
        $data = [];

        foreach ($this->modules as $module) {
            $data[] = [
                'name'        => $module->get_name(),
                'title'       => $module->get_title(),
                'icon'        => $module->get_icon(),
                'description' => $module->get_description(),
                'is_active'   => $this->is_active($module->get_name()),
                'is_headless' => $module->is_headless(),
                'is_mock'     => $module->is_mock(),
                'badge'       => $module->get_badge(),
                'url'         => $module->get_url(),
            ];
        }

        return $data;
    }

    /**
     * Returns a registered module by slug, or null if it doesn't exist.
     *
     * @param string $name The module slug.
     * @return Module|null
     */
    public function get_module(string $name): ?Module
    {
        return $this->modules[$name] ?? null;
    }

    /**
     * Returns every currently active module, keyed by slug.
     *
     * @return array<string, Module>
     */
    public function get_active_modules(): array
    {
        return $this->active_modules;
    }

    /**
     * Whether a module is currently active.
     *
     * @param string $name The module slug.
     * @return bool
     */
    public function is_active(string $name): bool
    {
        return isset($this->active_modules[$name]);
    }

    /**
     * Determines the active module set from the stored option (plus any
     * always-active modules) and initializes each one.
     *
     * @return void
     */
    public function init_modules(): void
    {
        $stored = (array) get_option(OptionKeys::ACTIVE_MODULES, []);

        foreach ($this->modules as $name => $module) {
            if ($module->is_always_active() || in_array($name, $stored, true)) {
                $this->active_modules[$name] = $module;
            }
        }

        foreach ($this->active_modules as $module) {
            $module->init();
            $module->register_routes();
            $module->register_assets();
        }
    }

    /**
     * Activates a module and persists it to the active-modules option.
     *
     * @param string $name The module slug.
     * @return void
     * @throws ModuleNotFoundException If no module matches `$name`.
     */
    public function enable_module(string $name): void
    {
        if (!isset($this->modules[$name])) {
            /* translators: %s: module slug. */
            $message = sprintf(__('Module %s not found.', 'elemacy'), $name);

            throw new ModuleNotFoundException(esc_html($message), Response::NOT_FOUND); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- second arg is an HTTP status code, not output.
        }

        $active = (array) get_option(OptionKeys::ACTIVE_MODULES, []);

        if (!in_array($name, $active, true)) {
            $active[] = $name;
            update_option(OptionKeys::ACTIVE_MODULES, $active);
            $this->modules[$name]->on_enable();
        }
    }

    /**
     * Deactivates a module and persists it to the active-modules option.
     *
     * @param string $name The module slug.
     * @return void
     * @throws ModuleNotFoundException If no module matches `$name`.
     */
    public function disable_module(string $name): void
    {
        if (!isset($this->modules[$name])) {
            /* translators: %s: module slug. */
            $message = sprintf(__('Module %s not found.', 'elemacy'), $name);

            throw new ModuleNotFoundException(esc_html($message), Response::NOT_FOUND); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- second arg is an HTTP status code, not output.
        }

        $stored = (array) get_option(OptionKeys::ACTIVE_MODULES, []);

        if (!in_array($name, $stored, true)) {
            return;
        }

        $active = array_values(array_diff($stored, [$name]));

        update_option(OptionKeys::ACTIVE_MODULES, $active);
        $this->modules[$name]->on_disable();
    }
}
