<?php

namespace Elemacy\Modules\Popups\Contracts;

defined('ABSPATH') || exit;

/**
 * Handed to {@see \Elemacy\Core\Hooks::POPUP_TRIGGERS_REGISTER_ACTION}
 * listeners. A working trigger replaces a locked placeholder of the same name;
 * any other name clash is rejected.
 *
 * @since 1.0.0
 * @api
 */
interface TriggerRegistrarInterface
{
    public function register(TriggerInterface $trigger): self;
}
