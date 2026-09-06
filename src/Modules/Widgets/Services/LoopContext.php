<?php

namespace Elemacy\Modules\Widgets\Services;

defined('ABSPATH') || exit;

use Elemacy\Modules\Widgets\Contracts\LoopItemInterface;

/**
 * The stack of loop items currently being rendered, innermost last. A stack
 * rather than a single slot so a loop item template that itself contains
 * another loop widget — or a page with more than one loop widget — nests
 * and unwinds correctly, with no leakage between them (see design.md D3).
 *
 * The loop render loop is the only writer: push() right before rendering
 * one item, pop() right after, wrapped in try/finally so an exception
 * mid-render can't leave a stale item on the stack.
 */
final class LoopContext
{
    /** @var LoopItemInterface[] */
    protected static array $stack = [];

    public static function push(LoopItemInterface $item): void
    {
        self::$stack[] = $item;
    }

    public static function pop(): void
    {
        array_pop(self::$stack);
    }

    public static function current(): ?LoopItemInterface
    {
        $count = count(self::$stack);

        return $count > 0 ? self::$stack[$count - 1] : null;
    }
}
