<?php

namespace Elemacy\Core\Admin;

defined('ABSPATH') || exit;

use Elemacy\Support\Brand;
use Elemacy\Support\Utils;

/**
 * The plugin's own top-level menu and its Core submenus. Modules add their
 * own submenus; AdminMenu orders and registers all of them on admin_menu.
 */
class CoreAdminMenu
{
    protected bool $registered = false;

    /**
     * Attaches this class's WordPress hooks. Idempotent: a second call does
     * nothing.
     */
    public function register_hooks(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        // On init, not earlier: the labels are translated.
        add_action('init', [$this, 'add_menus']);
        add_action('admin_menu', [AdminMenu::class, 'register']);
    }

    public function add_menus(): void
    {
        $plugin_menu_dto = new MenuDTO();
        $plugin_menu_dto->page_title = __('Elemacy', 'elemacy');
        $plugin_menu_dto->menu_title = __('Elemacy', 'elemacy');
        $plugin_menu_dto->menu_slug = 'elemacy';
        $plugin_menu_dto->icon_url = Brand::data_uri();
        AdminMenu::add_menu($plugin_menu_dto);

        $overview_menu_dto = new SubMenuDTO();
        $overview_menu_dto->page_title = __('Overview', 'elemacy');
        $overview_menu_dto->menu_title = __('Overview', 'elemacy');
        $overview_menu_dto->menu_slug = '';
        AdminMenu::add_submenu($overview_menu_dto);

        $modules_menu_dto = new SubMenuDTO();
        $modules_menu_dto->page_title = __('Modules', 'elemacy');
        $modules_menu_dto->menu_title = __('Modules', 'elemacy');
        $modules_menu_dto->menu_slug = 'modules';
        AdminMenu::add_submenu($modules_menu_dto);

        $library_menu_dto = new SubMenuDTO();
        $library_menu_dto->page_title = __('Template Library', 'elemacy');
        $library_menu_dto->menu_title = __('Template Library', 'elemacy');
        $library_menu_dto->menu_slug = 'library';
        AdminMenu::add_submenu($library_menu_dto);

        if (!Utils::is_pro_active()) {
            $upgrade_menu_dto = new SubMenuDTO();
            $upgrade_menu_dto->page_title = __('Upgrade to Pro', 'elemacy');
            $upgrade_menu_dto->menu_title = '<span class="elemacy-external-menu-link">' . esc_html__('Upgrade to Pro', 'elemacy') . '</span>';
            $upgrade_menu_dto->external_url = 'https://elemacy.com';
            $upgrade_menu_dto->position = 999;
            AdminMenu::add_submenu($upgrade_menu_dto);
        }
    }
}
