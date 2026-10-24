<?php

namespace Elemacy\Forms;

defined('ABSPATH') || exit;

/**
 * A lazily filled set of extensions by key: the loader (built-ins from a
 * config list, then the registration hook) runs on the first lookup, so a
 * request that never renders or submits a form builds none of them. A key is
 * registered once; a second registration is refused with a development
 * diagnostic and the first one stays.
 */
abstract class Registry
{
    public const KEY_PATTERN = '/\A[a-z0-9_-]{1,64}\z/';

    /** @var callable|null */
    private $loader;

    /** @var array<string, object> */
    private array $items = [];

    /**
     * @param callable $loader Receives the registry; called once, on the first lookup.
     */
    public function __construct(callable $loader)
    {
        $this->loader = $loader;
    }

    protected function add(string $key, object $item): bool
    {
        if (1 !== preg_match(self::KEY_PATTERN, $key)) {
            return $this->refuse(sprintf('"%s" is not a valid key: use 1-64 lowercase letters, digits, "_" or "-".', $key));
        }

        if (isset($this->items[$key])) {
            return $this->refuse(sprintf('"%s" is already registered.', $key));
        }

        $this->items[$key] = $item;

        return true;
    }

    protected function find(string $key): ?object
    {
        $this->load();

        return $this->items[$key] ?? null;
    }

    /** @return object[] In registration order. */
    protected function items(): array
    {
        $this->load();

        return array_values($this->items);
    }

    protected function refuse(string $reason): bool
    {
        _doing_it_wrong(static::class . '::register', esc_html($reason), '1.0.0');

        return false;
    }

    private function load(): void
    {
        if (null === $this->loader) {
            return;
        }

        $loader = $this->loader;
        $this->loader = null;

        $loader($this);
    }
}
