<?php

namespace Elemacy\TemplateLibrary\Contracts;

defined('ABSPATH') || exit;

use Elemacy\TemplateLibrary\TypeDefinition;

/**
 * Handed to {@see \Elemacy\Core\Hooks::LIBRARY_TYPES_REGISTER_ACTION}
 * listeners. Adds a library item type; a name already taken is rejected.
 *
 * @since 1.0.0
 * @api
 */
interface LibraryTypeRegistrarInterface
{
    public function register(TypeDefinition $definition): self;
}
