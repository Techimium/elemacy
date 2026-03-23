<?php

namespace Elemacy\Core;

abstract class Module
{
	abstract public function get_name(): string;

	abstract public function get_title(): string;

	abstract public function get_description(): string;

	public function get_dependencies(): array
	{
		return [];
	}

	abstract public function init();

	public function register_routes()
	{
	}

	public function register_assets()
	{
	}

	public function is_active(): bool
	{
		$active_modules = get_option('elemacy_active_modules', []);
		return in_array($this->get_name(), $active_modules, true);
	}

	public function is_always_active(): bool
	{
		return false;
	}

	protected function get_option(string $key, $default_value = null)
	{
		$option_key = 'elemacy_' . $this->get_name() . '_' . $key;
		return get_option($option_key, $default_value);
	}

	protected function update_option(string $key, $value): bool
	{
		$option_key = 'elemacy_' . $this->get_name() . '_' . $key;
		return update_option($option_key, $value);
	}

	protected function delete_option(string $key): bool
	{
		$option_key = 'elemacy_' . $this->get_name() . '_' . $key;
		return delete_option($option_key);
	}
}
