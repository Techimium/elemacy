<?php

namespace Elemacy\Modules\Popups;

defined('ABSPATH') || exit;

use Elemacy\Core\AdminMenu;
use Elemacy\Core\DTO\SubMenuDTO;
use Elemacy\Modules\Popups\Documents\DocumentManager;
use Elemacy\Modules\Popups\Services\EditorPreview;
use Elemacy\Modules\Popups\Services\PopupFrontendAssets;
use Elemacy\Modules\Popups\Services\PopupManager;
use Elemacy\Modules\Popups\Services\TriggerRuleBootstrap;
use Elemacy\Modules\Popups\Support\PopupTypes;
use Elemacy\Core\Module;
use Elemacy\Support\Utils;
use Elemacy\TemplateLibrary\TypeDefinition;
use Elemacy\TemplateLibrary\TypeRegistry;

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
        $this->register_types();

        (new DocumentManager())->register_hooks();
        (new EditorPreview())->register_hooks();
        (new PopupFrontendAssets());
        PopupManager::instance()->register_hooks();
        (new TriggerRuleBootstrap())->register();
    }

    public function register_routes()
    {
        require_once Utils::get_plugin_path('src/Modules/Popups/Config/api.php');
    }

    /**
     * The popup item types this module owns. The CPT itself is registered by
     * Core, so enabling/disabling the module no longer needs a rewrite flush.
     * Hooked on `init` because the labels are translated — calling __() before
     * `init` trips WP 6.7's "translation loaded too early" notice.
     */
    protected function register_types(): void
    {
        add_action('init', function () {
            $registry = TypeRegistry::instance();

            $types = [
                PopupTypes::POPUP    => __('Popup', 'elemacy'),
                PopupTypes::TOPBAR   => __('Top / Bottom Bar', 'elemacy'),
                PopupTypes::BANNER   => __('Banner', 'elemacy'),
                PopupTypes::FLOATING => __('Floating Element', 'elemacy'),
            ];

            foreach ($types as $name => $label) {
                $registry->register(new TypeDefinition($name, $label, 'popup', $this->get_name()));
            }
        });
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
}
