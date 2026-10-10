<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

use Elemacy\Core\Constants\OptionKeys;
use Elemacy\Http\Exceptions\ModuleNotFoundException;
use Elemacy\Http\Response;

class ModuleManager
{
    protected bool $initialized = false;

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
     * Determines the active module set from the stored preferences (plus any
     * always-active modules), then runs every active module's register
     * before any module's boot. Runs once per request.
     *
     * @return void
     */
    public function init_modules(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;
        $choices = $this->stored_choices();

        foreach ($this->modules as $name => $module) {
            if ($this->resolves_active($module, $choices)) {
                $this->active_modules[$name] = $module;
            }
        }

        $container = Container::instance();

        foreach ($this->active_modules as $module) {
            $module->register($container);
        }

        foreach ($this->active_modules as $module) {
            $module->boot();
            $module->register_routes();
            $module->register_assets();
        }
    }

    /**
     * Records an administrator's choice to activate a module.
     *
     * @param string $name The module slug.
     * @return void
     * @throws ModuleNotFoundException If no module matches `$name`.
     */
    public function enable_module(string $name): void
    {
        $this->record_choice($name, true);
    }

    /**
     * Records an administrator's choice to deactivate a module.
     *
     * @param string $name The module slug.
     * @return void
     * @throws ModuleNotFoundException If no module matches `$name`.
     */
    public function disable_module(string $name): void
    {
        $this->record_choice($name, false);
    }

    /**
     * Stores the choice even when it equals the module's default, so a later
     * release changing that default never overrides it. The lifecycle hooks
     * fire only when the module's effective state actually changes.
     *
     * @throws ModuleNotFoundException If no module matches `$name`.
     */
    protected function record_choice(string $name, bool $enabled): void
    {
        if (!isset($this->modules[$name])) {
            /* translators: %s: module slug. */
            $message = sprintf(__('Module %s not found.', 'elemacy'), $name);

            throw new ModuleNotFoundException(esc_html($message), Response::NOT_FOUND); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- second arg is an HTTP status code, not output.
        }

        $module = $this->modules[$name];
        $choices = $this->stored_choices();
        $was_active = $this->resolves_active($module, $choices);

        $choices[$name] = $enabled;
        update_option(OptionKeys::ACTIVE_MODULES, $choices);

        $is_active = $this->resolves_active($module, $choices);

        if ($is_active && !$was_active) {
            $module->on_enable();
        } elseif ($was_active && !$is_active) {
            $module->on_disable();
        }
    }

    /**
     * A module with no recorded choice follows its own declared default, so a
     * default-active module added by a later release reaches existing sites.
     *
     * @param Module              $module
     * @param array<string, bool> $choices
     */
    protected function resolves_active(Module $module, array $choices): bool
    {
        if ($module->is_always_active()) {
            return true;
        }

        $name = $module->get_name();

        return array_key_exists($name, $choices) ? $choices[$name] : $module->is_default_active();
    }

    /**
     * The stored `slug => bool` choices. A list of slugs is the earlier shape,
     * where every module it did not name was off: it reads as that same set,
     * every listed slug on and every other registered module off.
     *
     * @return array<string, bool>
     */
    protected function stored_choices(): array
    {
        $stored = get_option(OptionKeys::ACTIVE_MODULES, []);

        if (!is_array($stored) || [] === $stored) {
            return [];
        }

        if (array_keys($stored) !== range(0, count($stored) - 1)) {
            return array_filter(
                $stored,
                static fn($enabled, $name): bool => is_string($name) && is_bool($enabled),
                ARRAY_FILTER_USE_BOTH
            );
        }

        $choices = [];

        foreach ($this->modules as $name => $module) {
            if (!$module->is_always_active()) {
                $choices[$name] = false;
            }
        }

        foreach ($stored as $name) {
            if (is_string($name)) {
                $choices[$name] = true;
            }
        }

        return $choices;
    }
}
