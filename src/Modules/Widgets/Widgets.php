<?php
namespace Elemacy\Modules\Widgets;

if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\AdminMenu;
use Elemacy\Core\DTO\SubMenuDTO;
use Elemacy\Core\Module;
use Elemacy\Modules\Widgets\Services\FrontendAssets;
use Elemacy\Modules\Widgets\Services\WidgetManager;
use Elemacy\Support\Utils;

class Widgets extends Module
{
    public function get_name(): string
    {
        return 'widgets';
    }

    public function get_title(): string
    {
        return __('Widgets', 'elemacy');
    }

    public function get_icon(): string
    {
        return 'component';
    }

    public function get_description(): string
    {
        return __('Custom Elementor widgets to enhance your website.', 'elemacy');
    }

    public function init(): void
    {
        $this->register_admin_menu();
        new FrontendAssets();
        WidgetManager::instance();
        require_once Utils::get_plugin_path('src/Modules/Widgets/Config/ajax.php');
    }

    public function register_routes()
    {
        require_once Utils::get_plugin_path('src/Modules/Widgets/Config/api.php');
    }

    public function register_admin_menu()
    {
        add_action('init', function () {
            $submenu_dto = new SubMenuDTO();
            $submenu_dto->page_title = __('Widgets', 'elemacy');
            $submenu_dto->menu_title = __('Widgets', 'elemacy');
            $submenu_dto->menu_slug = 'widgets';
            AdminMenu::add_submenu($submenu_dto);
        });
    }
}
