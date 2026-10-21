<?php

namespace Elemacy\Loop\Rendering;

defined('ABSPATH') || exit;

use Elemacy\Loop\Support\LoopLimits;

/**
 * Refuses an *active* recursive template cycle — direct or indirect — and caps
 * nesting depth, without refusing a repeated template across sequential (non-
 * nested) items, which is entirely legal.
 */
final class TemplateRecursionGuard
{
    public const MAX_DEPTH = LoopLimits::MAX_DEPTH;

    /** @var int[] Outermost first; the last entry is the template rendering right now. */
    private static array $stack = [];

    /** @throws TemplateRecursionException If $template_id is already an active ancestor, or entering it would exceed MAX_DEPTH. */
    public static function enter(int $template_id): void
    {
        if (in_array($template_id, self::$stack, true)) {
            throw new TemplateRecursionException(sprintf('Template %d is already rendering in this chain — refusing an active recursive cycle.', $template_id)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, never rendered as HTML output.
        }

        if (count(self::$stack) >= self::MAX_DEPTH) {
            throw new TemplateRecursionException(sprintf('Loop template nesting exceeded the maximum depth of %d.', self::MAX_DEPTH)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, never rendered as HTML output.
        }

        self::$stack[] = $template_id;
    }

    public static function exit(): void
    {
        array_pop(self::$stack);
    }

    /** @return int[] Outermost first. */
    public static function stack(): array
    {
        return self::$stack;
    }

    public static function depth(): int
    {
        return count(self::$stack);
    }
}
