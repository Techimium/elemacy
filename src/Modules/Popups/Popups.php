<?php

namespace Elemacy\Modules\Popups;

defined('ABSPATH') || exit;

use Elemacy\Core\AdminMenu;
use Elemacy\Core\DTO\SubMenuDTO;
use Elemacy\Core\Hooks;
use Elemacy\Core\Module;
use Elemacy\Modules\Popups\Documents\DocumentManager;
use Elemacy\Modules\Popups\PostTypes\PopupPostType;
use Elemacy\Modules\Popups\Services\EditorPreview;
use Elemacy\Modules\Popups\Services\PopupFrontendAssets;
use Elemacy\Modules\Popups\Services\PopupManager;
use Elemacy\Modules\Popups\Services\TriggerRuleBootstrap;
use Elemacy\Support\Utils;

class Popups extends Module
{
    public function get_name(): string
    {
        return 'popups';
    }

    public function get_title(): string
    {
        return __('Popups & Floating Elements', 'elemacy');
    }

    public function get_icon(): string
    {
        return 'message-circle';
    }

    public function get_description(): string
    {
        return __('Build popups, top/bottom bars, banners and floating elements with display conditions and triggers.', 'elemacy');
    }

    public function init(): void
    {
        $this->register_admin_menu();
        PopupPostType::register();
        $this->exclude_from_conditions();

        (new DocumentManager())->register_hooks();
        (new EditorPreview())->register_hooks();
        (new PopupFrontendAssets());
        PopupManager::instance()->register_hooks();
        (new TriggerRuleBootstrap())->register();

        require_once Utils::get_plugin_path('src/Modules/Popups/Config/ajax.php');
    }

    public function register_routes()
    {
        require_once Utils::get_plugin_path('src/Modules/Popups/Config/api.php');
    }

    public function register_admin_menu()
    {
        add_action('init', function () {
            $submenu_dto = new SubMenuDTO();
            $submenu_dto->page_title = __('Popups', 'elemacy');
            $submenu_dto->menu_title = __('Popups', 'elemacy');
            $submenu_dto->menu_slug = 'popups';
            AdminMenu::add_submenu($submenu_dto);
        });
    }

    /**
     * Keep the popup CPT out of the display-condition target lists.
     */
    protected function exclude_from_conditions(): void
    {
        add_filter(\Elemacy\Core\Hooks::CONDITIONS_EXCLUDED_POST_TYPES_FILTER, function ($excluded) {
            $excluded = is_array($excluded) ? $excluded : [];
            $excluded[] = PopupPostType::POST_TYPE;

            return $excluded;
        });
    }
}
