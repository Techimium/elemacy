<?php

namespace Elemacy\Loop;

defined('ABSPATH') || exit;

use Elemacy\Loop\Address\AddressStep;
use Elemacy\Loop\Address\ItemAddress;
use Elemacy\Loop\Contracts\AddressableItemInterface;
use Elemacy\Loop\Contracts\LoopItemInterface;
use InvalidArgumentException;
use Throwable;

/**
 * The stack of loop frames currently being rendered, innermost last. Each
 * pushed item gets its address here: the enclosing frame's address plus the
 * item's own step.
 */
final class LoopContext
{
    /** @var LoopFrame[] */
    protected static array $stack = [];

    public static function push(LoopItemInterface $item, string $source_key = '', int $template_id = 0, int $index = 0, string $render_key = ''): LoopFrame
    {
        $frame = new LoopFrame($item, self::address_under(self::current_frame(), $item), $source_key, $template_id, $index, $render_key);
        self::$stack[] = $frame;

        return $frame;
    }

    public static function pop(): void
    {
        array_pop(self::$stack);
    }

    public static function current(): ?LoopItemInterface
    {
        $frame = self::current_frame();

        return $frame ? $frame->item() : null;
    }

    public static function current_frame(): ?LoopFrame
    {
        return self::ancestor(0);
    }

    /**
     * @param int $levels 0 is the current frame, 1 its parent, and so on.
     */
    public static function ancestor(int $levels): ?LoopFrame
    {
        $index = count(self::$stack) - 1 - $levels;

        return $levels >= 0 && $index >= 0 ? self::$stack[$index] : null;
    }

    /** @return LoopItemInterface[] Outermost first. */
    public static function stack(): array
    {
        return array_map(static fn(LoopFrame $frame): LoopItemInterface => $frame->item(), self::$stack);
    }

    /** @return LoopFrame[] Outermost first. */
    public static function frames(): array
    {
        return self::$stack;
    }

    /**
     * Runs $read with an ancestor item made current again: its native context
     * entered (in isolation, so the current item is untouched) and its frame
     * on top of the stack. Level 0 runs $read as is.
     *
     * @param int      $levels How many levels above the current item.
     * @param callable $read
     * @return mixed $read's result, or null when there is no item at that level.
     */
    public static function at_level(int $levels, callable $read)
    {
        if ($levels <= 0) {
            return $read();
        }

        $frame = self::ancestor($levels);

        if (null === $frame) {
            return null;
        }

        self::$stack[] = $frame;

        try {
            $frame->item()->enter();

            try {
                return $read();
            } finally {
                $frame->item()->exit();
            }
        } finally {
            array_pop(self::$stack);
        }
    }

    /** The address $item gets inside $parent, or null past the nesting limit. */
    public static function address_under(?LoopFrame $parent, LoopItemInterface $item): ?ItemAddress
    {
        $base = $parent ? $parent->address() : new ItemAddress();

        if (null === $base) {
            return null;
        }

        try {
            $step = $item instanceof AddressableItemInterface ? $item->get_address_step() : AddressStep::opaque($item->get_identity());
        } catch (Throwable $error) {
            // An item that cannot say where it lives still renders; it just
            // cannot be rebuilt as a parent.
            $step = AddressStep::opaque('unaddressable');
        }

        try {
            return $base->append($step);
        } catch (InvalidArgumentException $error) {
            return null;
        }
    }
}
