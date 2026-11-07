<?php

namespace Elemacy\Core\Contracts;

defined('ABSPATH') || exit;

/**
 * Handed to {@see \Elemacy\Core\Hooks::REGISTER_PROVIDERS_ACTION} listeners so
 * they can add a provider to the current request's register/boot cycle.
 *
 * @since 1.0.0
 * @api
 */
interface ProviderRegistrarInterface
{
    public function register_provider(ServiceProviderInterface $provider): void;
}
