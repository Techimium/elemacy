<?php
namespace Elemacy\Modules\ThemeBuilder;

if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\AdminMenu;
use Elemacy\Core\DTO\SubMenuDTO;
use Elemacy\Core\Module;
use Elemacy\Modules\ThemeBuilder\PostTypes\TemplatePostType;
use Elemacy\Modules\ThemeBuilder\Services\ConditionManager;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;
use Elemacy\Support\Utils;

class ThemeBuilder extends Module
{
    public function get_name(): string
    {
        return 'theme-builder';
    }

    public function get_title(): string
    {
        return __('Theme Builder', 'elemacy');
    }

    public function get_icon(): string
    {
        return 'layers';
    }

    public function get_description(): string
    {
        return __('Manage your site structure templates for a full theme experience.', 'elemacy');
    }

    public function init(): void
    {
        $this->register_admin_menu();
        TemplatePostType::register();
        ThemeBuilderManager::instance()->register_hooks();
        $this->register_conditions();
    }

    public function register_routes()
    {
        require_once Utils::get_plugin_path('src/Modules/ThemeBuilder/Config/api.php');
    }

    public function register_admin_menu()
    {
        add_action('init', function () {
            $submenu_dto = new SubMenuDTO();
            $submenu_dto->page_title = __('Theme Builder', 'elemacy');
            $submenu_dto->menu_title = __('Theme Builder', 'elemacy');
            $submenu_dto->menu_slug = 'theme-builder';
            AdminMenu::add_submenu($submenu_dto);
        });
    }

    /**
     * Register the conditions free ships, listed in Config/conditions.php.
     * The list includes mock conditions that elemacy-pro overrides (by name) with
     * real implementations when active.
     */
    protected function register_conditions(): void
    {
        $manager = ConditionManager::instance();

        foreach (require Utils::get_plugin_path('src/Modules/ThemeBuilder/Config/conditions.php') as $class) {
            $manager->register(new $class());
        }
    }
}
