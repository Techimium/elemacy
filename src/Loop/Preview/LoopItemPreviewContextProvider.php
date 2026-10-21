<?php

namespace Elemacy\Loop\Preview;

defined('ABSPATH') || exit;

use Elemacy\Elementor\Contracts\PreviewContextProviderInterface;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Contracts\NestedItemInterface;
use Elemacy\Loop\LoopContext;
use Elemacy\Loop\Support\LoopLimits;

/**
 * The loop-specific half of PreviewManager's own orchestration: pushing a
 * previewed item onto the shared LoopContext stack and entering its native
 * context, then reversing both on the way out. A nested item (a sub-repeater
 * row) brings its enclosing items along, outermost first, so ancestor reads
 * and loops nested in the template behave as they will on the page. Each frame
 * is pushed before it is entered, as LoopRenderer does.
 */
class LoopItemPreviewContextProvider implements PreviewContextProviderInterface
{
    /**
     * @param LoopItemInterface $item
     * @return LoopItemInterface[] The pushed and entered items, outermost first, carried back to leave().
     */
    public function enter($item)
    {
        $chain = [$item];
        $outermost = $item;

        for ($depth = 1; $depth < LoopLimits::MAX_DEPTH && $outermost instanceof NestedItemInterface; ++$depth) {
            $outermost = $outermost->get_parent_item();

            if (null === $outermost) {
                break;
            }

            array_unshift($chain, $outermost);
        }

        $entered = [];

        try {
            foreach ($chain as $link) {
                LoopContext::push($link);
                $entered[] = $link;
                $link->enter();
            }
        } catch (\Throwable $error) {
            // The item that failed undid its own setup; unwind the rest.
            array_pop($entered);
            LoopContext::pop();
            $this->leave($entered);

            return [];
        }

        return $entered;
    }

    /**
     * @param LoopItemInterface[] $context
     */
    public function leave($context): void
    {
        foreach (array_reverse((array) $context) as $item) {
            $item->exit();
            LoopContext::pop();
        }
    }

    public function is_inside_item(): bool
    {
        return null !== LoopContext::current();
    }
}
