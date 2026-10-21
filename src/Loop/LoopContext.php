<?php

namespace Elemacy\Loop;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\LoopItemInterface;

/** The stack of loop items currently being rendered, innermost last. */
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

    /** @return LoopItemInterface[] Outermost first. */
    public static function stack(): array
    {
        return self::$stack;
    }
}
