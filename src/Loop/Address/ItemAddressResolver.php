<?php

namespace Elemacy\Loop\Address;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\LoopItemInterface;

/**
 * Rebuilds the chain of loop items a signed address points to, root first,
 * each step from its already validated parent. One instance serves one
 * request: a prefix it already rebuilt is not resolved again.
 */
final class ItemAddressResolver
{
    private AddressStepResolverRegistry $registry;

    /** @var array<string, LoopItemInterface|null> Canonical prefix => rebuilt item. */
    private array $memo = [];

    public function __construct(?AddressStepResolverRegistry $registry = null)
    {
        $this->registry = $registry ?? AddressStepResolverRegistry::instance();
    }

    /**
     * @return LoopItemInterface[]|null The items outermost first, or null when
     *                                  any step can no longer be rebuilt.
     */
    public function resolve(ItemAddress $address): ?array
    {
        if (!$address->is_rebuildable()) {
            return null;
        }

        $items = [];
        $prefix = new ItemAddress();
        $parent = null;

        foreach ($address->steps() as $step) {
            $prefix = $prefix->append($step);
            $key = $prefix->canonical();

            if (!array_key_exists($key, $this->memo)) {
                $resolver = $this->registry->get($step->kind());
                $this->memo[$key] = $resolver ? $resolver->resolve($step, $parent) : null;
            }

            $parent = $this->memo[$key];

            if (null === $parent) {
                return null;
            }

            $items[] = $parent;
        }

        return $items;
    }

    /**
     * One hash over every step's content fingerprint, so any change anywhere
     * in the chain is detected.
     *
     * @param ItemAddress         $address
     * @param LoopItemInterface[] $items The address's items, outermost first.
     */
    public function fingerprint(ItemAddress $address, array $items): string
    {
        $parts = [];

        foreach ($address->steps() as $index => $step) {
            $resolver = $this->registry->get($step->kind());
            $item = $items[$index] ?? null;
            $parts[] = $step->kind() . '=' . ($resolver && $item ? (string) $resolver->fingerprint($item) : '');
        }

        return substr(md5(implode('|', $parts)), 0, 16);
    }
}
