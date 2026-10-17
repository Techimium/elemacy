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
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Placement\PlacementResolverRegistry;
use Elemacy\Loop\Preview\LoopItemPreviewContextProvider;
use Elemacy\Loop\Rendering\LoopItemStyles;
use Elemacy\TemplateLibrary\LibraryBootstrap;
use Elemacy\TemplateLibrary\TemplateResolver;
use Elemacy\TemplateLibrary\TypeRegistry;

/**
 * Shared services outside any module: the bootstraps and managers boot
 * builds (admin menu, HTTP routes, library, conditions, Elementor
 * integration), plus a container binding for every shared registry so a
 * container-built service always receives the populated singleton, and for
 * the contracts those bootstraps depend on.
 */
class CoreServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
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

        $container->bind(PreviewContextProviderInterface::class, static fn () => new LoopItemPreviewContextProvider());

        // Resolvers load on the registry's first lookup (a picker search), so a
        // request that never runs one builds none of them and fires no action.
        $container->bind(QueryResolverRegistry::class, static fn () => QueryResolverRegistry::from_config());
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
        $container->make(PreviewManager::class)->register_hooks();
        $container->make(PreviewAssets::class)->register_hooks();
        $container->make(QueryControlManager::class)->register_hooks();
        $container->make(TemplateAssetsRegistrar::class)->register_hooks();
    }
}
