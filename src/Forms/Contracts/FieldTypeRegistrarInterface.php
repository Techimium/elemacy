<?php

namespace Elemacy\Forms\Contracts;

defined('ABSPATH') || exit;

/**
 * Handed to {@see \Elemacy\Core\Hooks::FORM_FIELD_TYPES_REGISTER_ACTION}
 * listeners. Adds a form field type.
 *
 * @since 1.0.0
 * @api
 */
interface FieldTypeRegistrarInterface
{
    /** @return bool False when refused: the key is already taken. */
    public function register(FieldTypeInterface $type): bool;
}
