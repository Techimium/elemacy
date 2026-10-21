<?php

namespace Elemacy\Loop\Address;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\LoopItemInterface;

/**
 * Rebuilds the loop item one kind of address step points to, from its already
 * rebuilt and validated parent, and checks again that it may still be shown.
 * Registered by kind on AddressStepResolverRegistry.
 */
interface AddressStepResolverInterface
{
    /** The step kind this resolver owns (see AddressStep::kind()). */
    public function get_kind(): string;

    /**
     * @param AddressStep            $step A step of this resolver's kind, from a signed address.
     * @param LoopItemInterface|null $parent The rebuilt enclosing item; null at the root.
     * @return LoopItemInterface|null Null when the item no longer exists, is no longer public, or does not belong under $parent.
     */
    public function resolve(AddressStep $step, ?LoopItemInterface $parent): ?LoopItemInterface;

    /**
     * A short value that changes whenever the item's content or position
     * changes, so a later page never runs under a parent that changed after
     * its first page was served. Null when this kind has none.
     */
    public function fingerprint(LoopItemInterface $item): ?string;
}
