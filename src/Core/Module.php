<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

abstract class Module
{
	abstract public function get_name(): string;

	abstract public function get_title(): string;

	abstract public function get_icon(): string;

	abstract public function get_description(): string;

	abstract public function init();

	public function register_routes() {}

	public function register_assets() {}

	public function on_enable(): void {}

	public function on_disable(): void {}

	public function is_always_active(): bool
	{
		return false;
	}

	public function is_headless(): bool
	{
		return false;
	}

	public function get_badge(): ?string
	{
		return null;
	}

	public function get_url(): ?string
	{
		return null;
	}

	public function is_mock(): bool
	{
		return false;
	}
}
