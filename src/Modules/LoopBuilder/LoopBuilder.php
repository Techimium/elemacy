<?php

namespace Elemacy\Modules\LoopBuilder;

if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\Hooks;
use Elemacy\Core\Module;
use Elemacy\Loop\Address\AddressStepResolverRegistry;
use Elemacy\Loop\Address\Resolvers\PostStepResolver;
use Elemacy\Loop\Address\Resolvers\TermStepResolver;
use Elemacy\Loop\Address\Resolvers\UserStepResolver;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Placement\NestedLoopPlacementResolver;
use Elemacy\Loop\Placement\PagePlacementResolver;
use Elemacy\Loop\Placement\PlacementResolverRegistry;
use Elemacy\Modules\LoopBuilder\DataSources\CurrentQueryDataSource;
use Elemacy\Modules\LoopBuilder\DataSources\PostsDataSource;
use Elemacy\Modules\LoopBuilder\DataSources\TaxonomyDataSource;
use Elemacy\Modules\LoopBuilder\DataSources\UsersDataSource;
use Elemacy\Modules\LoopBuilder\Documents\DocumentManager;
use Elemacy\Modules\LoopBuilder\Documents\LoopDocument;
use Elemacy\Modules\LoopBuilder\Services\FrontendAssets;
use Elemacy\Support\Utils;
use Elemacy\TemplateLibrary\TypeDefinition;
use Elemacy\TemplateLibrary\TypeRegistry;

/**
 * Loop Builder: the Loop Grid and Loop Carousel widgets, the Loop Item
 * template they render, the built-in data sources and public pagination. The
 * loop engine itself (src/Loop) is shared, because Theme Builder, Popups,
 * Dynamic Tags and Pro use it too.
 *
 * @internal
 */
class LoopBuilder extends Module
{
    public function get_name(): string
    {
        return 'loop-builder';
    }

    public function is_default_active(): bool
    {
        return true;
    }

    public function is_headless(): bool
    {
        return true;
    }

    public function get_title(): string
    {
        return __('Loop Builder', 'elemacy');
    }

    public function get_icon(): string
    {
        return 'layout-panel-top';
    }

    public function get_description(): string
    {
        return __('Design a Loop Item template once and show posts, terms or users in a grid or carousel.', 'elemacy');
    }

    public function boot(): void
    {
        new DocumentManager();
        new FrontendAssets();

        $this->register_types();
        $this->register_data_sources();
        $this->register_placement_resolvers();
        $this->register_address_step_resolvers();

        require_once Utils::get_plugin_path('src/Modules/LoopBuilder/Config/ajax.php');
    }

    public function get_widgets(): array
    {
        return require Utils::get_plugin_path('src/Modules/LoopBuilder/Config/widgets.php');
    }

    /**
     * The loop data sources Loop Grid / Loop Carousel / AJAX pagination
     * consume. Registers the built-in sources directly, then fires
     * LOOP_DATA_SOURCES_REGISTER_ACTION so add-ons can register more.
     */
    protected function register_data_sources(): void
    {
        add_action('init', static function () {
            $registry = LoopDataSourceRegistry::instance();
            $registry->register(new PostsDataSource());
            $registry->register(new CurrentQueryDataSource());
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
     * The resolvers that rebuild the built-in loop items (posts, terms, users)
     * from a signed address, so a later page of a loop nested inside one of
     * them renders inside its real parent.
     */
    protected function register_address_step_resolvers(): void
    {
        add_action('init', static function () {
            $registry = AddressStepResolverRegistry::instance();
            $registry->register(new PostStepResolver());
            $registry->register(new TermStepResolver());
            $registry->register(new UserStepResolver());

            do_action(Hooks::LOOP_ADDRESS_STEP_RESOLVERS_REGISTER_ACTION, $registry);
        }, 99);
    }

    /**
     * The "Loop Item" block library type, owned by this module because its
     * widgets consume it. Registered on `init` (the label is translated); the
     * Template Library page surfaces it via the shared `block` group.
     */
    protected function register_types(): void
    {
        add_action('init', function () {
            TypeRegistry::instance()->register(
                new TypeDefinition('loop', __('Loop Item', 'elemacy'), 'block', $this->get_name(), LoopDocument::class)
            );
        });
    }
}
