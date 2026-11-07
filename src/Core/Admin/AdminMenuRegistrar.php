<?php

namespace Elemacy\Core\Admin;

defined('ABSPATH') || exit;

use Elemacy\Core\Contracts\AdminMenuRegistrarInterface;
use Elemacy\Core\Admin\DTO\SubMenuDTO;

/**
 * The published face of AdminMenu's request-wide submenu list.
 *
 * @internal
 */
class AdminMenuRegistrar implements AdminMenuRegistrarInterface
{
    public function add_submenu(SubMenuDTO $submenu): void
    {
        AdminMenu::add_submenu($submenu);
    }
}
