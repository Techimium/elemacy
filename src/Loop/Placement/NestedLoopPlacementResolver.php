<?php

namespace Elemacy\Loop\Placement;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\PlacementResolverInterface;
use Elemacy\Loop\Rendering\LoopTemplateRenderStack;

/**
 * A Loop Grid widget authored inside a loop item template — the owner is the
 * template actively rendering right now, not an independently reachable page.
 */
final class NestedLoopPlacementResolver implements PlacementResolverInterface
{
    const MAX_DEPTH = 10;

    public function get_key(): string
    {
        return 'nested_loop';
    }

    public function resolve(int $owner_id): ?array
    {
        $lineage = LoopTemplateRenderStack::stack();
        if (empty($lineage) || end($lineage) !== $owner_id || count($lineage) > self::MAX_DEPTH) {
            return null;
        }

        return ['lineage' => $lineage];
    }
}
