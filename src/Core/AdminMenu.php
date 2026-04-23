<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

use Elemacy\Core\DTO\MenuDTO;
use Elemacy\Core\DTO\SubMenuDTO;

class AdminMenu
{
    protected static $menus = [];
    protected static $submenus = [];

    public static function add_menu(MenuDTO $menu_dto)
    {
        static::$menus[] = $menu_dto;
    }

    public static function add_submenu(SubMenuDTO $submenu_dto)
    {
        static::$submenus[] = $submenu_dto;
    }

    public static function register()
    {
        foreach (static::$menus as $menu) {
            add_menu_page(
                $menu->page_title,
                $menu->menu_title,
                $menu->capabilty,
                $menu->menu_slug,
                $menu->callback ? $menu->callback : static::render(),
                $menu->icon_url,
                $menu->position
            );
        }

        foreach (static::$submenus as $submenu) {
            add_submenu_page(
                $submenu->parent_slug,
                $submenu->page_title,
                $submenu->menu_title,
                $submenu->capabilty,
                $submenu->parent_slug . '#' . $submenu->menu_slug,
                $submenu->callback ? $submenu->callback : static::render(),
                $submenu->position
            );
        }

        foreach (static::$menus as $menu) {
            remove_submenu_page($menu->menu_slug, $menu->menu_slug);
        }
    }

    protected static function render()
    {
        return function () {
            echo '<div id="elemacy_root"></div>';
        };
    }
}
