<?php

namespace Elemacy\Modules\Widgets;

if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\AdminMenu;
use Elemacy\Core\DTO\SubMenuDTO;
use Elemacy\Core\Hooks;
use Elemacy\Core\Module;
use Elemacy\Modules\Widgets\DataSources\PostsDataSource;
use Elemacy\Modules\Widgets\DataSources\TaxonomyDataSource;
use Elemacy\Modules\Widgets\DataSources\UsersDataSource;
use Elemacy\Modules\Widgets\Documents\LoopDocument;
use Elemacy\Modules\Widgets\Services\LoopDataSourceRegistry;
use Elemacy\Modules\Widgets\Services\Placement\NestedLoopPlacementResolver;
use Elemacy\Modules\Widgets\Services\Placement\PagePlacementResolver;
use Elemacy\Modules\Widgets\Services\PlacementResolverRegistry;
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
        return __('Extend the Elementor editor with extra widgets built by Elemacy.', 'elemacy');
    }

    public function get_providers(): array
    {
        return [new WidgetsServiceProvider()];
    }

    public function init(): void
    {
        $this->register_admin_menu();
        $this->register_types();
        $this->register_data_sources();
        $this->register_placement_resolvers();
        require_once Utils::get_plugin_path('src/Modules/Widgets/Config/ajax.php');
    }

    /**
     * The loop data sources Loop Grid / Loop Carousel / AJAX pagination
     * consume. Registers the built-in Posts source directly, then fires
     * LOOP_DATA_SOURCES_REGISTER_ACTION so add-ons can register more —
     * mirrors register_locations() in ThemeBuilder.
     *
     * Deferred to `init` (priority 99), mirroring
     * Popups\Services\TriggerRuleBootstrap::register(): pro extensions hook
     * this action via Hooks::LOADED_ACTION, which only fires once this
     * module's own init() (called from Elemacy::init()'s synchronous
     * init_modules() pass, on `plugins_loaded`) has already returned. Firing
     * the action directly from here — before LOADED_ACTION even runs — would
     * leave no listener in place yet. Running on `init` instead guarantees
     * any extension that hooked in during `plugins_loaded` is ready before
     * the action fires, without depending on hook priority between plugins.
     */
    protected function register_data_sources(): void
    {
        add_action('init', static function () {
            $registry = LoopDataSourceRegistry::instance();

            $registry->register(new PostsDataSource());
            $registry->register(new TaxonomyDataSource());
            $registry->register(new UsersDataSource());

            do_action(Hooks::LOOP_DATA_SOURCES_REGISTER_ACTION, $registry);
        }, 99);
    }

    /**
     * The placement resolvers public pagination uses to prove an owner
     * document is the one actually resolved for the current request. Widgets
     * registers the two placements it owns directly (an ordinary page, and a
     * loop template actively rendering a nested Loop Grid); other modules
     * contribute their own via LOOP_PLACEMENT_RESOLVERS_REGISTER_ACTION,
     * mirroring register_data_sources() above.
     */
    protected function register_placement_resolvers(): void
    {
        add_action('init', static function () {
            $registry = PlacementResolverRegistry::instance();

            $registry->register(new PagePlacementResolver());
            $registry->register(new NestedLoopPlacementResolver());

            do_action(Hooks::LOOP_PLACEMENT_RESOLVERS_REGISTER_ACTION, $registry);
        }, 99);
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
                new TypeDefinition('loop', __('Loop Item', 'elemacy'), 'block', $this->get_name(), LoopDocument::class)
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
