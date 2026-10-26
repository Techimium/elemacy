<?php

namespace Elemacy\Modules\Widgets\Widgets\NavMenu;

defined('ABSPATH') || exit;

/** The site's WordPress menus, as the Menu control lists them. */
final class MenuOptions
{
    /** @return array<string, string> Menu name by slug, in WordPress's order. */
    public static function all(): array
    {
        $options = [];

        foreach (wp_get_nav_menus() as $menu) {
            $options[$menu->slug] = $menu->name;
        }

        return $options;
    }
}
