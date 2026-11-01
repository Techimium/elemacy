<?php

namespace Elemacy\Forms\Contracts;

defined('ABSPATH') || exit;

/**
 * Handed to {@see \Elemacy\Core\Hooks::FORM_SOURCES_REGISTER_ACTION}
 * listeners. Adds a form source.
 *
 * @since 1.0.0
 * @api
 */
interface FormSourceRegistrarInterface
{
    /** @return bool False when refused: the key is already taken. */
    public function register(FormSourceInterface $source): bool;
}
