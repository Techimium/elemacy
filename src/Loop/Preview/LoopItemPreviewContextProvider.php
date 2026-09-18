<?php

namespace Elemacy\Loop\Preview;

defined('ABSPATH') || exit;

use Elemacy\Core\Contracts\PreviewContextProviderInterface;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\LoopContext;

/**
 * The loop-specific half of PreviewManager's own orchestration (design.md
 * D7): pushing a previewed item onto the shared LoopContext stack and
 * entering its native context, then reversing both on the way out — moved
 * here, unchanged in behavior, from what used to be inline in
 * Core\Preview\PreviewManager itself.
 */
class LoopItemPreviewContextProvider implements PreviewContextProviderInterface
{
    /**
     * @param LoopItemInterface $item
     * @return LoopItemInterface The same item, carried back to leave() —
     *                           LoopContext's stack has no "peek without
     *                           popping" accessor, so the item is returned
     *                           here rather than re-fetched in leave().
     */
    public function enter($item)
    {
        $item->enter();
        LoopContext::push($item);

        return $item;
    }

    /**
     * @param LoopItemInterface $context
     */
    public function leave($context): void
    {
        LoopContext::pop();
        $context->exit();
    }
}
