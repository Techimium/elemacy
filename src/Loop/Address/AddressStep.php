<?php

namespace Elemacy\Loop\Address;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * One step of an item address: what kind of thing the item points to (a post,
 * a term, an ACF row, ...) and where it is within its parent. The locator's
 * meaning belongs to the resolver registered for the kind; core never reads it.
 */
final class AddressStep
{
    /** The step of an item no resolver can rebuild; it still counts toward depth. */
    public const OPAQUE = 'opaque';

    private string $kind;
    private string $locator;

    /** @throws InvalidArgumentException On a malformed kind or an empty locator. */
    public function __construct(string $kind, string $locator)
    {
        if (!preg_match('/\A[a-z0-9_]{1,32}\z/', $kind) || '' === $locator) {
            throw new InvalidArgumentException('An address step needs a lowercase kind and a locator.');
        }

        $this->kind = $kind;
        $this->locator = $locator;
    }

    public static function opaque(string $identity): self
    {
        return new self(self::OPAQUE, '' !== $identity ? $identity : '-');
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function locator(): string
    {
        return $this->locator;
    }

    public function is_opaque(): bool
    {
        return self::OPAQUE === $this->kind;
    }

    public function canonical(): string
    {
        return $this->kind . ':' . rawurlencode($this->locator);
    }

    public static function from_canonical(string $part): ?self
    {
        $parts = explode(':', $part, 2);

        if (2 !== count($parts)) {
            return null;
        }

        try {
            return new self($parts[0], rawurldecode($parts[1]));
        } catch (InvalidArgumentException $error) {
            return null;
        }
    }
}
