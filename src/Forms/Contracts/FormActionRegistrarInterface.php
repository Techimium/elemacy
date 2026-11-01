<?php

namespace Elemacy\Forms\Contracts;

defined('ABSPATH') || exit;

/**
 * Handed to {@see \Elemacy\Core\Hooks::FORM_ACTIONS_REGISTER_ACTION}
 * listeners. Adds a form submit action.
 *
 * @since 1.0.0
 * @api
 */
interface FormActionRegistrarInterface
{
    /** @return bool False when refused: the key is already taken. */
    public function register(FormActionInterface $action): bool;
}
