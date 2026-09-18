<?php

namespace Elemacy\Modules\Widgets\Services;

defined('ABSPATH') || exit;

/**
 * Tracks the chain of loop template IDs currently rendering, outermost
 * first. Lets a nested Loop Grid widget's placement resolver prove its owner
 * is the template actually being rendered right now, not an arbitrary
 * published loop template ID. A later phase replaces this with the full
 * render-identity pipeline (design.md D8); this stack only records template
 * ownership for placement validation.
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
