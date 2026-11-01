<?php

namespace Elemacy\Core\Contracts;

defined('ABSPATH') || exit;

use Elemacy\Core\Module;

/**
 * Handed to {@see \Elemacy\Core\Hooks::REGISTER_MODULES_ACTION} listeners so
 * they can add a feature module, keyed by its slug.
 *
 * @since 1.0.0
 * @api
 */
interface ModuleRegistrarInterface
{
    public function register(Module $module): void;
}
