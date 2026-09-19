<?php

namespace Elemacy\Modules\Popups;

defined('ABSPATH') || exit;

use Elemacy\Core\Admin\AdminMenu;
use Elemacy\Core\Container;
use Elemacy\Core\Admin\SubMenuDTO;
use Elemacy\Core\Hooks;
use Elemacy\Modules\Popups\Documents\DocumentManager;
use Elemacy\Modules\Popups\Services\EditorPreview;
use Elemacy\Modules\Popups\Services\Placement\PopupPlacementResolver;
use Elemacy\Modules\Popups\Services\PopupDisplayResolver;
use Elemacy\Modules\Popups\Services\PopupFrontendAssets;
use Elemacy\Modules\Popups\Services\PopupManager;
use Elemacy\Modules\Popups\Services\RuleManager;
use Elemacy\Modules\Popups\Services\TriggerManager;
use Elemacy\Modules\Popups\Services\TriggerRuleBootstrap;
use Elemacy\Modules\Popups\Support\DocumentDisplay;
use Elemacy\Modules\Popups\Support\PopupTypes;
use Elemacy\Core\Module;
use Elemacy\Loop\Placement\PlacementResolverRegistry;
use Elemacy\Support\Utils;
use Elemacy\TemplateLibrary\LibraryChanged;
use Elemacy\TemplateLibrary\LibraryPostType;
use Elemacy\TemplateLibrary\TypeDefinition;
use Elemacy\TemplateLibrary\TypeRegistry;

class Popups extends Module
{
    public function get_name(): string
    {
        return 'popups';
    }

    public function is_default_active(): bool
    {
        return true;
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

    public function register(Container $container): void
    {
        foreach ([PopupManager::class, PopupDisplayResolver::class, RuleManager::class, TriggerManager::class] as $registry) {
            $container->bind($registry, static fn () => $registry::instance());
        }
    }

    public function boot(): void
    {
        (new DocumentManager())->register_hooks();
        (new EditorPreview())->register_hooks();
        new PopupFrontendAssets();
        PopupManager::instance()->register_hooks();
        (new TriggerRuleBootstrap())->register();
        $this->register_cache_invalidation();

        $this->register_admin_menu();
        $this->register_types();
        $this->register_placement_resolver();
    }

    /**
     * Popups owns resetting its own request-level caches when a library
     * document changes or the acting site changes mid-request.
     */
    protected function register_cache_invalidation(): void
    {
        $reset = static function ($post_id): void {
            if (get_post_type($post_id) === LibraryPostType::POST_TYPE) {
                PopupManager::instance()->reset();
                DocumentDisplay::forget((int) $post_id);
            }
        };

        add_action('save_post', $reset);
        add_action('before_delete_post', $reset);
        add_action('trashed_post', $reset);
        add_action('untrashed_post', $reset);

        add_action(Hooks::LIBRARY_CHANGED_ACTION, static function (LibraryChanged $event): void {
            PopupManager::instance()->reset();
            DocumentDisplay::forget($event->id);
        });

        add_action('switch_blog', static function (): void {
            PopupManager::instance()->reset();
            DocumentDisplay::reset();
        });
    }

    /**
     * Contributes the Popup placement resolver into Widgets' public
     * pagination registry (see ThemeBuilder::register_placement_resolver()
     * for the same pattern).
     */
    protected function register_placement_resolver(): void
    {
        add_action(Hooks::LOOP_PLACEMENT_RESOLVERS_REGISTER_ACTION, static function (PlacementResolverRegistry $registry) {
            $registry->register(new PopupPlacementResolver());
        });
    }

    public function register_routes()
    {
        require_once Utils::get_plugin_path('src/Modules/Popups/Config/api.php');
    }

    /**
     * The popup item types this module owns. The CPT itself is registered by
     * Core, so enabling/disabling the module no longer needs a rewrite flush.
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
