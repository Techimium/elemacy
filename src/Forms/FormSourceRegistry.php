<?php

namespace Elemacy\Forms;

use Elemacy\Core\Hooks;
use Elemacy\Forms\Contracts\FormSourceInterface;

defined('ABSPATH') || exit;

/**
 * The builders whose forms the engine can submit. Free ships none here: the
 * module that renders a builder's forms registers its source on
 * Hooks::FORM_SOURCES_REGISTER_ACTION, so a disabled module's forms are
 * simply unavailable.
 */
final class FormSourceRegistry extends Registry
{
    public static function from_hook(): self
    {
        return new self(static function (self $registry): void {
            do_action(Hooks::FORM_SOURCES_REGISTER_ACTION, $registry);
        });
    }

    /** @return bool False when refused. */
    public function register(FormSourceInterface $source): bool
    {
        return $this->add($source->key(), $source);
    }

    public function get(string $key): ?FormSourceInterface
    {
        $source = $this->find($key);

        return $source instanceof FormSourceInterface ? $source : null;
    }
}
