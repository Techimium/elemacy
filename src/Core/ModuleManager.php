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

	public function register(Module $module): void
	{
		$this->modules[$module->get_name()] = $module;
	}

	public function get_modules(): array
	{
		return $this->modules;
	}

	public function to_array(): array
	{
		$data = [];

		foreach ($this->modules as $module) {
			$data[] = [
				'name'           => $module->get_name(),
				'title'          => $module->get_title(),
				'icon'           => $module->get_icon(),
				'description'    => $module->get_description(),
				'is_active'      => $this->is_active($module->get_name()),
				'is_headless'    => $module->is_headless(),
				'is_placeholder' => $module->is_placeholder(),
				'badge'          => $module->get_badge(),
				'url'            => $module->get_url(),
			];
		}

		return $data;
	}

	public function get_module(string $name): ?Module
	{
		return $this->modules[$name] ?? null;
	}

	public function get_active_modules(): array
	{
		return $this->active_modules;
	}

	public function is_active(string $name): bool
	{
		return isset($this->active_modules[$name]);
	}

	public function init_modules(): void
	{
		$stored = get_option(OptionKeys::ACTIVE_MODULES, []);

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

	public function enable_module(string $name): void
	{
		if (!isset($this->modules[$name])) {
			throw new ModuleNotFoundException( // phpcs:disable line
				/* translators: %s: module name */
				sprintf(__('Module "%s" not found.', 'elemacy'), $name), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.I18n.MissingTranslatorsComment
				Response::NOT_FOUND // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}

		$active = get_option(OptionKeys::ACTIVE_MODULES, []);

		if (!in_array($name, $active, true)) {
			$active[] = $name;
			update_option(OptionKeys::ACTIVE_MODULES, $active);
			$this->modules[$name]->on_enable();
		}
	}

	public function disable_module(string $name): void
	{
		if (!isset($this->modules[$name])) {
			throw new ModuleNotFoundException( // phpcs:disable line
				/* translators: %s: module name */
				sprintf(__('Module "%s" not found.', 'elemacy'), $name), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.I18n.MissingTranslatorsComment
				Response::NOT_FOUND // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}

		$stored = get_option(OptionKeys::ACTIVE_MODULES, []);

		if (!in_array($name, $stored, true)) {
			return;
		}

		$active = array_values(array_diff($stored, [$name]));

		update_option(OptionKeys::ACTIVE_MODULES, $active);
		$this->modules[$name]->on_disable();
	}
}
