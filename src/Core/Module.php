<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

use Elemacy\Core\Contracts\ServiceProviderInterface;

/**
 * A feature module is a service provider with an identity: register binds what
 * it shares, boot attaches its behavior. ModuleManager runs every active
 * module's register before any module's boot. See docs/ARCHITECTURE.md § Boot
 * lifecycle.
 */
abstract class Module implements ServiceProviderInterface
{
    abstract public function get_name(): string;

    abstract public function get_title(): string;

    abstract public function get_icon(): string;

    abstract public function get_description(): string;

    /**
     * Binds the services this module shares. Nothing by default.
     */
    public function register(Container $container): void
    {
    }

    /**
     * Attaches this module's hooks and registrations.
     */
    abstract public function boot(): void;

    public function register_routes() {
    }

    public function register_assets() {
    }

    public function on_enable(): void {
    }

    public function on_disable(): void {
    }

    public function is_always_active(): bool
    {
        return false;
    }

    /**
     * Whether this module is switched on when a site is seeded, i.e. a site
     * that has no saved module preferences yet. Each module owns this answer
     * so that seeding never needs a separate list of slugs to drift out of
     * sync with get_name(). Unlike is_always_active(), it is only ever
     * consulted at seeding time: an administrator can disable a
     * default-active module afterwards like any other.
     */
    public function is_default_active(): bool
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
