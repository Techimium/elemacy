<?php

namespace Elemacy\Core\Providers;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionEvaluator;
use Elemacy\Conditions\ConditionManager;
use Elemacy\Conditions\ConditionsBootstrap;
use Elemacy\Core\Admin\AdminScripts;
use Elemacy\Core\Container;
use Elemacy\Core\Contracts\ServiceProviderInterface;
use Elemacy\Core\FrontendScripts;
use Elemacy\Core\RequestState;
use Elemacy\Core\Admin\PluginLinks;
use Elemacy\Elementor\Controls\Query\QueryControlManager;
use Elemacy\Elementor\Preview\PreviewAssets;
use Elemacy\Elementor\Preview\PreviewManager;
use Elemacy\Elementor\Rendering\TemplateAssetsRegistrar;
use Elemacy\Elementor\Rendering\TemplateRenderer;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Placement\PlacementResolverRegistry;
use Elemacy\Loop\Rendering\LoopItemStyles;
use Elemacy\TemplateLibrary\LibraryBootstrap;
use Elemacy\TemplateLibrary\TemplateResolver;
use Elemacy\TemplateLibrary\TypeRegistry;

/**
 * Shared services outside any module: the bootstraps and managers boot
 * constructs, plus a container binding for every shared registry so a
 * container-built service always receives the populated singleton.
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
    }

    public function boot(): void
    {
        RequestState::register_hooks();
        RequestState::register(TemplateRenderer::class, [TemplateRenderer::class, 'reset']);
        RequestState::register(LoopItemStyles::class, [LoopItemStyles::class, 'reset']);

        (new AdminScripts())->register_hooks();
        (new FrontendScripts())->register_hooks();
        (new PluginLinks())->register_hooks();
        (new ConditionsBootstrap())->register_hooks();
        (new LibraryBootstrap())->register_hooks();
        (new PreviewManager())->register_hooks();
        (new PreviewAssets())->register_hooks();
        (new QueryControlManager())->register_hooks();
        (new TemplateAssetsRegistrar())->register_hooks();
    }
}
