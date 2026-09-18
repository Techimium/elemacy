<?php

namespace Elemacy\Loop\Rendering;

defined('ABSPATH') || exit;

/**
 * The stack of render identities currently being rendered, innermost last —
 * the render-identity counterpart to LoopContext's item stack (design.md
 * D8), used by the dynamic-CSS layer (task 8.7) to scope per-item styles to
 * exactly the occurrence currently rendering, not merely the current item's
 * domain identity.
 *
 * The render loop is the only writer: push() right before rendering one
 * item, pop() right after, wrapped in try/finally so an exception mid-render
 * can't leave a stale identity on the stack.
 */
final class LoopRenderContext
{
    /** @var LoopRenderIdentity[] */
    private static array $stack = [];

    public static function push(LoopRenderIdentity $identity): void
    {
        self::$stack[] = $identity;
    }

    public static function pop(): void
    {
        array_pop(self::$stack);
    }

    public static function current(): ?LoopRenderIdentity
    {
        $count = count(self::$stack);

        return $count > 0 ? self::$stack[$count - 1] : null;
    }

    /** @return LoopRenderIdentity[] Outermost first. */
    public static function stack(): array
    {
        return self::$stack;
    }

    public static function depth(): int
    {
        return count(self::$stack);
    }
}
