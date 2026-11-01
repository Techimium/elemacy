<?php

namespace Elemacy\Core\Contracts;

defined('ABSPATH') || exit;

use Elemacy\Core\Admin\DTO\SubMenuDTO;

/**
 * Adds a page under Free's admin menu. Call it before `admin_menu` runs.
 *
 * @since 1.0.0
 * @api
 */
interface AdminMenuRegistrarInterface
{
    public function add_submenu(SubMenuDTO $submenu): void;
}
