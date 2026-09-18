<?php

namespace Elemacy\Modules\Widgets;

if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\Admin\AdminMenu;
use Elemacy\Core\Container;
use Elemacy\Core\Admin\SubMenuDTO;
use Elemacy\Core\Hooks;
use Elemacy\Core\Module;
use Elemacy\Modules\Widgets\DataSources\PostsDataSource;
use Elemacy\Modules\Widgets\DataSources\TaxonomyDataSource;
use Elemacy\Modules\Widgets\DataSources\UsersDataSource;
use Elemacy\Modules\Widgets\Documents\DocumentManager;
use Elemacy\Modules\Widgets\Documents\LoopDocument;
use Elemacy\Modules\Widgets\Forms\Actions\EmailAction;
use Elemacy\Modules\Widgets\Forms\Services\PublishedFormDefinitionResolver;
use Elemacy\Modules\Widgets\Forms\Services\SubmissionRateLimiter;
use Elemacy\Modules\Widgets\Forms\Services\SubmissionService;
use Elemacy\Modules\Widgets\Forms\Services\SubmissionValidator;
use Elemacy\Modules\Widgets\Services\EditorAssets;
use Elemacy\Modules\Widgets\Services\FrontendAssets;
use Elemacy\Modules\Widgets\Services\WidgetManager;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Placement\NestedLoopPlacementResolver;
use Elemacy\Loop\Placement\PagePlacementResolver;
use Elemacy\Loop\Placement\PlacementResolverRegistry;
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

    public function register(Container $container): void
    {
        $container->bind(WidgetManager::class, static fn () => WidgetManager::instance());

        // The submission pipeline's action list is configuration, so a
        // factory owns it rather than reflection.
        $container->bind(SubmissionService::class, static function (Container $container) {
            return new SubmissionService(
                $container->make(PublishedFormDefinitionResolver::class),
                $container->make(SubmissionValidator::class),
                $container->make(SubmissionRateLimiter::class),
                [$container->make(EmailAction::class)]
            );
        });
    }

    public function boot(): void
    {
        new DocumentManager();
        new FrontendAssets();
        new EditorAssets();
        WidgetManager::instance();

        $this->register_admin_menu();
        $this->register_types();
        $this->register_data_sources();
        $this->register_placement_resolvers();
        require_once Utils::get_plugin_path('src/Modules/Widgets/Config/ajax.php');
    }

    /**
     * The loop data sources Loop Grid / Loop Carousel / AJAX pagination
     * consume. Registers the built-in Posts source directly, then fires
     * LOOP_DATA_SOURCES_REGISTER_ACTION so add-ons can register more — mirrors
     * register_locations() in ThemeBuilder.
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
     * document is the one actually resolved for the current request.
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
