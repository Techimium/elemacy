<?php

namespace Elemacy\Loop\Address;

defined('ABSPATH') || exit;

use Elemacy\Loop\Support\LoopLimits;
use InvalidArgumentException;

/**
 * Where a rendered loop item lives: its enclosing item's address plus one step,
 * outermost first. Immutable. The canonical string is only for boundaries (a
 * signed token, a hash); code passes the object.
 */
final class ItemAddress
{
    /** @var AddressStep[] */
    private array $steps;

    /**
     * @param AddressStep[] $steps Outermost first.
     * @throws InvalidArgumentException When deeper than LoopLimits::MAX_DEPTH.
     */
    public function __construct(array $steps = [])
    {
        if (count($steps) > LoopLimits::MAX_DEPTH) {
            throw new InvalidArgumentException('An item address cannot be deeper than the nesting limit.');
        }

        $this->steps = array_values($steps);
    }

    /** @throws InvalidArgumentException When the step would exceed the nesting limit. */
    public function append(AddressStep $step): self
    {
        return new self(array_merge($this->steps, [$step]));
    }

    public function parent(): self
    {
        return new self(array_slice($this->steps, 0, -1));
    }

    /** @return AddressStep[] Outermost first. */
    public function steps(): array
    {
        return $this->steps;
    }

    public function last(): ?AddressStep
    {
        return $this->steps ? $this->steps[count($this->steps) - 1] : null;
    }

    public function depth(): int
    {
        return count($this->steps);
    }

    public function is_empty(): bool
    {
        return [] === $this->steps;
    }

    /** Whether every step can be rebuilt by a resolver. */
    public function is_rebuildable(): bool
    {
        foreach ($this->steps as $step) {
            if ($step->is_opaque()) {
                return false;
            }
        }

        return !$this->is_empty();
    }

    public function canonical(): string
    {
        return implode('/', array_map(static fn(AddressStep $step): string => $step->canonical(), $this->steps));
    }

    /** A short token safe for CSS classes and ids. */
    public function hash(): string
    {
        return substr(md5($this->canonical()), 0, 12);
    }

    /** @return self|null Null for a malformed or too deep string. */
    public static function from_canonical(string $canonical): ?self
    {
        if ('' === $canonical) {
            return new self();
        }

        $steps = [];

        foreach (explode('/', $canonical) as $part) {
            $step = AddressStep::from_canonical($part);

            if (null === $step) {
                return null;
            }

            $steps[] = $step;
        }

        try {
            return new self($steps);
        } catch (InvalidArgumentException $error) {
            return null;
        }
    }
}
