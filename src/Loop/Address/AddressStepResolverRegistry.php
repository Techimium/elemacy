<?php

namespace Elemacy\Loop\Address;

defined('ABSPATH') || exit;

use Elemacy\Core\Capabilities\RegistryIdentifierGuard;

/**
 * In-memory registry of the address step resolvers, keyed by step kind.
 * Populated during the Widgets module's init and via
 * Hooks::LOOP_ADDRESS_STEP_RESOLVERS_REGISTER_ACTION; never persisted.
 */
final class AddressStepResolverRegistry
{
    private static ?self $instance = null;

    /** @var array<string, AddressStepResolverInterface> */
    private array $resolvers = [];

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function register(AddressStepResolverInterface $resolver): self
    {
        RegistryIdentifierGuard::assert_registrable(
            'Loop address step resolver',
            $resolver->get_kind(),
            $this->resolvers[$resolver->get_kind()] ?? null,
            $resolver
        );

        $this->resolvers[$resolver->get_kind()] = $resolver;

        return $this;
    }

    public function get(string $kind): ?AddressStepResolverInterface
    {
        return AddressStep::OPAQUE === $kind ? null : ($this->resolvers[$kind] ?? null);
    }

    /** @return array<string, AddressStepResolverInterface> */
    public function all(): array
    {
        return $this->resolvers;
    }
}
