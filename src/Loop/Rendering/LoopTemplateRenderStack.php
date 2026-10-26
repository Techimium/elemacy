<?php

namespace Elemacy\Loop\Rendering;

defined('ABSPATH') || exit;

/**
 * Tracks the chain of loop template IDs currently rendering, outermost first.
 * Lets a nested Loop Grid widget's placement resolver prove its owner is the
 * template actually being rendered right now, not an arbitrary published loop
 * template ID.
 */
final class LoopTemplateRenderStack
{
    /** @var int[] */
    private static array $stack = [];

    public static function push(int $template_id): void
    {
        self::$stack[] = $template_id;
    }

    public static function pop(): void
    {
        array_pop(self::$stack);
    }

    /** @return int[] Outermost first; the last entry is the template rendering right now. */
    public static function stack(): array
    {
        return self::$stack;
    }
}
