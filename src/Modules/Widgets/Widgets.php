<?php
namespace Elemacy\Modules\Widgets;

if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\AdminMenu;
use Elemacy\Core\DTO\SubMenuDTO;
use Elemacy\Core\Module;
use Elemacy\Modules\Widgets\Services\EditorAssets;
use Elemacy\Modules\Widgets\Services\FrontendAssets;
use Elemacy\Modules\Widgets\Services\WidgetManager;
use Elemacy\Support\Utils;
use Elemacy\TemplateLibrary\TypeDefinition;
use Elemacy\TemplateLibrary\TypeRegistry;

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
        $this->register_types();
        new FrontendAssets();
        new EditorAssets();
        WidgetManager::instance();
        require_once Utils::get_plugin_path('src/Modules/Widgets/Config/ajax.php');
    }

    /**
     * The Widgets module owns the "Loop Item" block library type because it ships
     * the Loop Grid/Carousel widgets that consume it. Registered on `init` (the
     * label is translated); the Core Template Library page surfaces it via the
     * shared `block` group.
     */
    protected function register_types(): void
    {
        add_action('init', function () {
            TypeRegistry::instance()->register(
                new TypeDefinition('loop', __('Loop Item', 'elemacy'), 'block', $this->get_name())
            );
        });
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
