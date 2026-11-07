<?php

namespace Elemacy\Core\Providers;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionEvaluator;
use Elemacy\Conditions\ConditionManager;
use Elemacy\Conditions\ConditionsBootstrap;
use Elemacy\Core\Admin\AdminScripts;
use Elemacy\Core\Admin\CoreAdminMenu;
use Elemacy\Core\Container;
use Elemacy\Core\Contracts\ServiceProviderInterface;
use Elemacy\Core\FrontendScripts;
use Elemacy\Core\RequestState;
use Elemacy\Elementor\Contracts\PreviewContextProviderInterface;
use Elemacy\Core\Admin\PluginLinks;
use Elemacy\Elementor\Controls\Query\QueryControlManager;
use Elemacy\Elementor\Controls\Query\QueryResolverRegistry;
use Elemacy\Elementor\Preview\PreviewAssets;
use Elemacy\Elementor\Preview\PreviewManager;
use Elemacy\Elementor\Rendering\TemplateAssetsRegistrar;
use Elemacy\Elementor\Rendering\TemplateRenderer;
use Elemacy\Http\HttpBootstrap;
use Elemacy\Core\ModuleManager;
use Elemacy\Widgets\Services\WidgetSettings;
use Elemacy\Widgets\WidgetRegistry;
use Elemacy\Widgets\WidgetsBootstrap;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Placement\PlacementResolverRegistry;
use Elemacy\Loop\Preview\LoopItemPreviewContextProvider;
use Elemacy\Loop\Rendering\LoopItemStyles;
use Elemacy\TemplateLibrary\LibraryBootstrap;
use Elemacy\TemplateLibrary\TemplateResolver;
use Elemacy\TemplateLibrary\TypeRegistry;
use Elemacy\Core\Contracts\ContainerInterface;
use Elemacy\Core\Elemacy;
use Elemacy\Core\Admin\AdminMenuRegistrar;
use Elemacy\Core\Contracts\ProviderRegistrarInterface;
use Elemacy\Core\Contracts\ModuleRegistrarInterface;
use Elemacy\Core\Contracts\ModuleStatusInterface;
use Elemacy\Core\Contracts\AdminMenuRegistrarInterface;
use Elemacy\Conditions\Contracts\ConditionRegistrarInterface;
use Elemacy\Loop\Contracts\LoopDataSourceRegistrarInterface;
use Elemacy\Loop\Contracts\AddressStepResolverRegistrarInterface;
use Elemacy\Loop\Address\AddressStepResolverRegistry;
use Elemacy\Integrations\Acf\Contracts\AcfRowCursorInterface;
use Elemacy\Integrations\Acf\AcfCursor;
use Elemacy\TemplateLibrary\Contracts\LibraryTypeRegistrarInterface;
use Elemacy\Loop\Contracts\PlacementResolverRegistrarInterface;
use Elemacy\Elementor\Contracts\QueryResolverRegistrarInterface;
use Elemacy\Elementor\Contracts\TemplateAssetsRegistrarInterface;

/**
 * Shared services outside any module: the bootstraps and managers boot
 * builds (admin menu, HTTP routes, library, conditions, widget platform,
 * Elementor integration), plus a container binding for every shared registry so a
 * container-built service always receives the populated singleton, and for
 * the contracts those bootstraps depend on.
 *
 * @internal
 */
class CoreServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $registries = [
            TypeRegistry::class,
            TemplateResolver::class,
            LoopDataSourceRegistry::class,
            PlacementResolverRegistry::class,
            ConditionManager::class,
            ConditionEvaluator::class,
        ];

        foreach ($registries as $registry) {
            $container->bind($registry, static fn () => $registry::instance());
        }

        // One widget list for Elementor registration and the Widgets screen alike.
        $container->bind(WidgetRegistry::class, static fn (Container $container) => new WidgetRegistry($container->singleton(ModuleManager::class), new WidgetSettings()));

        $container->bind(PreviewContextProviderInterface::class, static fn () => new LoopItemPreviewContextProvider());

        // Resolvers load on the registry's first lookup (a picker search), so a
        // request that never runs one builds none of them and fires no action.
        $container->bind(QueryResolverRegistry::class, static fn () => QueryResolverRegistry::from_config());

        // Published contracts (docs/CONTRACTS.md § PHP extension contract):
        // each resolves to the live instance Free itself uses, never a copy.
        $container->bind(ProviderRegistrarInterface::class, static fn () => Elemacy::get_instance());
        $container->bind(ModuleRegistrarInterface::class, static fn (Container $container) => $container->singleton(ModuleManager::class));
        $container->bind(ModuleStatusInterface::class, static fn (Container $container) => $container->singleton(ModuleManager::class));
        $container->bind(AdminMenuRegistrarInterface::class, static fn () => new AdminMenuRegistrar());
        $container->bind(ConditionRegistrarInterface::class, static fn () => ConditionManager::instance());
        $container->bind(LoopDataSourceRegistrarInterface::class, static fn () => LoopDataSourceRegistry::instance());
        $container->bind(AddressStepResolverRegistrarInterface::class, static fn () => AddressStepResolverRegistry::instance());
        $container->bind(AcfRowCursorInterface::class, static fn () => new AcfCursor(), Container::FRESH);
        $container->bind(LibraryTypeRegistrarInterface::class, static fn () => TypeRegistry::instance());
        $container->bind(PlacementResolverRegistrarInterface::class, static fn () => PlacementResolverRegistry::instance());
        $container->bind(QueryResolverRegistrarInterface::class, static fn (Container $container) => $container->singleton(QueryResolverRegistry::class));
        $container->bind(TemplateAssetsRegistrarInterface::class, static fn (Container $container) => $container->singleton(TemplateAssetsRegistrar::class));
    }

    public function boot(): void
    {
        RequestState::register_hooks();
        RequestState::register(TemplateRenderer::class, [TemplateRenderer::class, 'reset']);
        RequestState::register(LoopItemStyles::class, [LoopItemStyles::class, 'reset']);

        // Built through the container, not `new`, so a provider's register()
        // can override any of them and none needs an edit here when it
        // gains a dependency.
        $container = Container::instance();

        $container->make(CoreAdminMenu::class)->register_hooks();
        $container->make(HttpBootstrap::class)->register_hooks();
        $container->make(AdminScripts::class)->register_hooks();
        $container->make(FrontendScripts::class)->register_hooks();
        $container->make(PluginLinks::class)->register_hooks();
        $container->make(ConditionsBootstrap::class)->register_hooks();
        $container->make(LibraryBootstrap::class)->register_hooks();
        $container->make(WidgetsBootstrap::class)->register_hooks();
        $container->make(PreviewManager::class)->register_hooks();
        $container->make(PreviewAssets::class)->register_hooks();
        $container->make(QueryControlManager::class)->register_hooks();
        // Shared, so TemplateAssetsRegistrarInterface resolves to this hooked instance.
        $container->singleton(TemplateAssetsRegistrar::class)->register_hooks();
    }
}
