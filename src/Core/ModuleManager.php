<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

use WP_Error;
use Elemacy\Core\Constants\OptionKeys;

class ModuleManager
{
	protected array $modules = [];
	protected array $active_modules = [];
	protected array $initialized_modules = [];

	public function register(Module $module): void
	{
		$module_name = $module->get_name();
		$this->modules[$module_name] = $module;
	}

	public function get_modules(): array
	{
		return $this->modules;
	}

	public function get_module(string $name): ?Module
	{
		return $this->modules[$name] ?? null;
	}

	public function get_active_modules(): array
	{
		return $this->active_modules;
	}

	public function is_active(string $module_name): bool
	{
		return isset($this->active_modules[$module_name]);
	}

	public function enable_module(string $module_name)
	{
		if (!isset($this->modules[$module_name])) {
			return new WP_Error('module_not_found', __('Module not found.', 'elemacy'));
		}

		$dependencies_met = $this->check_dependencies($module_name);
		if (is_wp_error($dependencies_met)) {
			return $dependencies_met;
		}

		$active_modules = get_option(OptionKeys::ACTIVE_MODULES, []);
		if (!in_array($module_name, $active_modules, true)) {
			$active_modules[] = $module_name;
			update_option(OptionKeys::ACTIVE_MODULES, $active_modules);
		}

		$this->load_active_modules();

		return true;
	}

	public function disable_module(string $module_name)
	{
		$dependents = $this->get_dependent_modules($module_name);
		if (!empty($dependents)) {
			return new WP_Error(
				'has_dependents',
				sprintf(
					/* translators: %s: List of dependent modules */
					__('Cannot disable module. The following modules depend on it: %s', 'elemacy'),
					implode(', ', $dependents)
				)
			);
		}

		$active_modules = get_option(OptionKeys::ACTIVE_MODULES, []);
		$active_modules = array_diff($active_modules, array($module_name));
		update_option(OptionKeys::ACTIVE_MODULES, $active_modules);

		$this->load_active_modules();

		return true;
	}

	public function init_modules(): void
	{
		$this->load_active_modules();
		$this->initialize_modules();
	}

	protected function load_active_modules(): void
	{
		$active_module_names = get_option(OptionKeys::ACTIVE_MODULES, []);
		$this->active_modules = [];

		foreach ($active_module_names as $module_name) {
			if (isset($this->modules[$module_name])) {
				$this->active_modules[$module_name] = $this->modules[$module_name];
			}
		}

		$this->active_modules = array_merge($this->active_modules, $this->get_default_modules());
	}

	protected function get_default_modules(): array
	{
		$default_modules = [];

		foreach ($this->modules as $module) {
			if ($module->is_always_active()) {
				$default_modules[$module->get_name()] = $module;
			}
		}

		return $default_modules;
	}

	protected function initialize_modules(): void
	{
		$sorted_modules = $this->sort_by_dependencies($this->active_modules);

		foreach ($sorted_modules as $module) {
			$module_name = $module->get_name();
			if (!isset($this->initialized_modules[$module_name])) {
				if (!$this->has_active_dependencies($module)) {
					_doing_it_wrong(
						__METHOD__,
						sprintf(
							'Module "%s" has unmet dependencies and will not be initialized. Ensure all required modules are active.',
							esc_html($module_name)
						),
						ELEMACY_VERSION
					);
					continue;
				}
				$module->init();
				$module->register_routes();
				$module->register_assets();
				$this->initialized_modules[$module_name] = true;
			}
		}
	}

	private function has_active_dependencies(Module $module): bool
	{
		foreach ($module->get_dependencies() as $dependency) {
			if (!isset($this->active_modules[$dependency])) {
				return false;
			}
		}

		return true;
	}

	protected function check_dependencies(string $module_name)
	{
		$module = $this->modules[$module_name];
		$dependencies = $module->get_dependencies();

		foreach ($dependencies as $dependency) {
			if (!isset($this->modules[$dependency])) {
				return new WP_Error(
					'dependency_not_found',
					/* translators: %s: Module name */
					sprintf(__('Required dependency "%s" not found.', 'elemacy'), $dependency)
				);
			}

			if (!$this->is_active($dependency)) {
				return new WP_Error(
					'dependency_not_active',
					/* translators: %s: Module name */
					sprintf(__('Required dependency "%s" is not active.', 'elemacy'), $dependency)
				);
			}
		}

		return true;
	}

	protected function get_dependent_modules(string $module_name): array
	{
		$dependents = [];

		foreach ($this->active_modules as $name => $module) {
			if (in_array($module_name, $module->get_dependencies(), true)) {
				$dependents[] = $name;
			}
		}

		return $dependents;
	}

	protected function sort_by_dependencies(array $modules): array
	{
		$sorted = [];
		$visited = [];

		foreach ($modules as $module) {
			$this->visit_module($module, $modules, $visited, $sorted);
		}

		return $sorted;
	}

	protected function visit_module(Module $module, array $all_modules, array &$visited, array &$sorted): void
	{
		$module_name = $module->get_name();

		if (isset($visited[$module_name])) {
			return;
		}

		$visited[$module_name] = true;

		foreach ($module->get_dependencies() as $dependency) {
			if (isset($all_modules[$dependency])) {
				$this->visit_module($all_modules[$dependency], $all_modules, $visited, $sorted);
			}
		}

		$sorted[] = $module;
	}
}
