<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

use Elemacy\Core\Contracts\ServiceProviderInterface;

abstract class Module
{
    abstract public function get_name(): string;

    abstract public function get_title(): string;

    abstract public function get_icon(): string;

    abstract public function get_description(): string;

    abstract public function init();

    /**
     * The service providers this module's own collaborators are obtained
     * through, instead of ad hoc construction inside init() (design.md D7).
     * ModuleManager registers-then-boots every returned provider exactly
     * once per request, only for a module that ends up active — the same
     * two-phase discipline Core/Elemacy.php already applies to its own
     * providers, scoped to this one module's composition instead of the
     * whole plugin.
     *
     * @return ServiceProviderInterface[]
     */
    public function get_providers(): array
    {
        return [];
    }

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
