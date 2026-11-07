<?php

namespace Elemacy\Core\Contracts;

defined('ABSPATH') || exit;

/**
 * Whether a feature module is active on the current site.
 *
 * @since 1.0.0
 * @api
 */
interface ModuleStatusInterface
{
    public function is_active(string $name): bool;
}
