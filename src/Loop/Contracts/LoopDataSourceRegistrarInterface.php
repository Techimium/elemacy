<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

/**
 * Handed to {@see \Elemacy\Core\Hooks::LOOP_DATA_SOURCES_REGISTER_ACTION}
 * listeners. A working source replaces a locked placeholder of the same key;
 * any other key clash is rejected.
 *
 * @since 1.0.0
 * @api
 */
interface LoopDataSourceRegistrarInterface
{
    public function register(LoopDataSourceInterface $source): self;
}
