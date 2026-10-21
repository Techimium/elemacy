<?php

namespace Elemacy\Loop\Placement;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\PlacementResolverInterface;

/** Contributed placement resolvers, tried in registration order. */
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
     * The first matching contributed placement, with the request's host facts
     * (HostFacts: `post_id`, `term_id`) merged in. Those two names belong to
     * this registry, so a resolver can neither omit nor redefine them.
     *
     * @param int $owner_id Owner document already passing PublicRenderPolicy::assert_owner().
     * @return array{type: string, facts: array}|null
     */
    public function resolve_current(int $owner_id): ?array
    {
        foreach ($this->resolvers as $key => $resolver) {
            $facts = $resolver->resolve($owner_id);
            if (null !== $facts) {
                unset($facts[HostFacts::POST_ID], $facts[HostFacts::TERM_ID]);

                return [
                    'type' => $key,
                    'facts' => $facts + HostFacts::current(),
                ];
            }
        }

        return null;
    }
}
