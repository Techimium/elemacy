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
                $menu->capability,
                $menu->menu_slug,
                $menu->callback ? $menu->callback : static::render(),
                $menu->icon_url,
                $menu->position
            );
        }

        foreach (static::sorted_submenus() as $submenu) {
            $slug = $submenu->external_url
                ? $submenu->external_url
                : $submenu->parent_slug . '#' . $submenu->menu_slug;

            add_submenu_page(
                $submenu->parent_slug,
                $submenu->page_title,
                $submenu->menu_title,
                $submenu->capability,
                $slug,
                $submenu->external_url ? null : ($submenu->callback ? $submenu->callback : static::render()),
                $submenu->position
            );
        }

        foreach (static::$menus as $menu) {
            remove_submenu_page($menu->menu_slug, $menu->menu_slug);
        }
    }

    protected static function sorted_submenus(): array
    {
        $default_position = 100;
        $decorated = [];
        foreach (static::$submenus as $index => $submenu) {
            $position = $submenu->position === null ? $default_position : $submenu->position;
            $decorated[] = [$position, $index, $submenu];
        }

        usort($decorated, function ($a, $b) {
            return $a[0] === $b[0] ? $a[1] <=> $b[1] : $a[0] <=> $b[0];
        });

        return array_column($decorated, 2);
    }

    protected static function render()
    {
        return function () {
            echo '<div id="elemacy_root"></div>';
        };
    }
}
