<?php

namespace Elemacy\Core\Contracts;

defined('ABSPATH') || exit;

/**
 * The service container an extension receives in
 * {@see ServiceProviderInterface::register()}: where it binds its own
 * services and resolves the contracts Free publishes. Resolve published
 * contracts only, never a Free implementation class.
 *
 * @since 1.0.0
 * @api
 */
interface ContainerInterface
{
    /** One instance per request, built on first resolve. */
    public const SHARED = 'shared';

    /** A new instance on every resolve. */
    public const FRESH = 'fresh';

    /**
     * Whether a shared instance of the contract is already cached.
     */
    public function has(string $class_name): bool;

    /**
     * Registers a factory for a contract. The factory receives this container.
     *
     * @param class-string             $contract
     * @param callable(self): object   $factory
     * @param self::SHARED|self::FRESH $lifetime
     */
    public function bind(string $contract, callable $factory, string $lifetime = self::SHARED): void;

    /**
     * Whether a contract has a registered factory.
     */
    public function bound(string $contract): bool;

    /**
     * A new instance, unless the contract is bound SHARED.
     */
    public function make(string $class_name): object;

    /**
     * The shared instance, unless the contract is bound FRESH.
     */
    public function singleton(string $class_name): object;
}
