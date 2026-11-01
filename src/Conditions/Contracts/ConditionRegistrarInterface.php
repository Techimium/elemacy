<?php

namespace Elemacy\Conditions\Contracts;

defined('ABSPATH') || exit;

/**
 * Handed to {@see \Elemacy\Core\Hooks::CONDITIONS_REGISTER_ACTION} listeners.
 * A working condition replaces a locked placeholder of the same name; any
 * other name clash is rejected.
 *
 * @since 1.0.0
 * @api
 */
interface ConditionRegistrarInterface
{
    public function register(ConditionInterface $condition): self;
}
