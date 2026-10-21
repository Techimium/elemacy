<?php

namespace Elemacy\Loop\Address\Resolvers;

defined('ABSPATH') || exit;

use Elemacy\Loop\Address\AddressStep;
use Elemacy\Loop\Address\AddressStepResolverInterface;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Items\TermLoopItem;
use Elemacy\Loop\Placement\HostFacts;

/** `term:{id}` — a term of a publicly viewable taxonomy. */
final class TermStepResolver implements AddressStepResolverInterface
{
    public function get_kind(): string
    {
        return 'term';
    }

    public function resolve(AddressStep $step, ?LoopItemInterface $parent): ?LoopItemInterface
    {
        $id = ctype_digit($step->locator()) ? (int) $step->locator() : 0;
        $term = $id > 0 ? get_term($id) : null;

        return HostFacts::is_public_term($term) ? new TermLoopItem($term) : null;
    }

    public function fingerprint(LoopItemInterface $item): ?string
    {
        return null;
    }
}
