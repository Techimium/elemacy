<?php

namespace Elemacy\Modules\Widgets\Services;

defined('ABSPATH') || exit;

use Elemacy\Modules\Widgets\Contracts\PlacementResolverInterface;
use Elemacy\Modules\Widgets\Contracts\PublicContextPlacementResolverInterface;

/**
 * Contributed placement resolvers, tried in registration order. Each owning
 * module registers its own resolver through Hooks::LOOP_PLACEMENT_RESOLVERS_REGISTER_ACTION
 * (see Widgets::register_placement_resolvers()); this registry never imports
 * a concrete builder manager itself.
 */
final class PlacementResolverRegistry
{
    private static ?self $instance = null;

    /** @var array<string, PlacementResolverInterface> */
    private array $resolvers = [];

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function register(PlacementResolverInterface $resolver): void
    {
        $this->resolvers[$resolver->get_key()] = $resolver;
    }

    /** @return array<string, PlacementResolverInterface> */
    public function all(): array
    {
        return $this->resolvers;
    }

    /**
     * @param int $owner_id Owner document already passing PublicRenderPolicy::assert_owner().
     * @return array{type: string, facts: array}|null The first matching contributed placement.
     */
    public function resolve_current(int $owner_id): ?array
    {
        return $this->resolve($owner_id, false);
    }

    /** Only call after reconstructing an authenticated, server-signed frontend context. */
    public function resolve_public_context(int $owner_id): ?array
    {
        return $this->resolve($owner_id, true);
    }

    private function resolve(int $owner_id, bool $public_context): ?array
    {
        foreach ($this->resolvers as $key => $resolver) {
            $facts = $public_context && $resolver instanceof PublicContextPlacementResolverInterface
                ? $resolver->resolve_public_context($owner_id)
                : $resolver->resolve($owner_id);
            if (null !== $facts) {
                return [
                    'type' => $key,
                    'facts' => $facts
                ];
            }
        }

        return null;
    }
}
