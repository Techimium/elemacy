<?php

namespace Elemacy\Core\Contracts;

defined('ABSPATH') || exit;

use Elemacy\Core\Container;

/**
 * A composition unit registered through {@see
 * \Elemacy\Core\Hooks::REGISTER_PROVIDERS_ACTION} .
 */
interface ServiceProviderInterface
{
    /**
     * Stores container bindings and stable feature descriptors. Must not
     * construct Elementor classes, evaluate translated labels, or attach
     * WordPress hooks — those happen in boot(), at the WordPress/Elementor
     * phase they actually require.
     */
    public function register(Container $container): void;

    /**
     * Attaches this provider's WordPress callbacks at the native phases
     * they require. The composition root guarantees this runs at most once
     * per request, so an implementation does not need to guard against
     * being booted twice; one that already does stays correct.
     */
    public function boot(): void;
}
