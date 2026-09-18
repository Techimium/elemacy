<?php

namespace Elemacy\Core\Providers;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionsBootstrap;
use Elemacy\Core\AdminScripts;
use Elemacy\Core\Container;
use Elemacy\Core\Contracts\ServiceProviderInterface;
use Elemacy\Core\FrontendScripts;
use Elemacy\Core\PluginLinks;
use Elemacy\Core\Controls\Query\QueryControlManager;
use Elemacy\Core\Preview\PreviewAssets;
use Elemacy\Core\Preview\PreviewManager;
use Elemacy\Core\Rendering\TemplateAssetsRegistrar;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Pagination\PublicRenderPolicy;
use Elemacy\Modules\Widgets\Services\PlacementResolverRegistry;
use Elemacy\TemplateLibrary\LibraryBootstrap;
use Elemacy\TemplateLibrary\TypeRegistry;

/**
 * The shared services `Elemacy::init_core_components()` used to construct
 * directly, plus (task 6.6) one explicit contract binding.
 *
 * boot() is idempotent at this provider's own level: none of these classes
 * were changed to stop registering their own hooks in their constructor
 * (that is task 6.3's job), so idempotency here means never constructing
 * any of them a second time, which a $booted guard alone already
 * guarantees regardless of what each constructor itself does.
 */
class CoreServiceProvider implements ServiceProviderInterface
{
    protected bool $booted = false;

    /**
     * PublicRenderPolicy is resolved through the Container as a constructor
     * dependency of AjaxPaginationController (Container::make(), via
     * AjaxRouter — task 6.6). Without this explicit binding, plain
     * reflection would auto-wire its own TypeRegistry/LoopDataSourceRegistry/
     * PlacementResolverRegistry constructor parameters by constructing a
     * brand new, disconnected instance of each (`new TypeRegistry()`) rather
     * than the shared `::instance()` singleton every registration actually
     * populates — silently breaking every lookup. This factory is
     * responsible for exactly that: using the real shared instances design.md
     * D7 says a binding, not the reflection path, should own.
     */
    public function register(Container $container): void
    {
        $container->bind(PublicRenderPolicy::class, static function (): PublicRenderPolicy {
            return new PublicRenderPolicy(
                TypeRegistry::instance(),
                LoopDataSourceRegistry::instance(),
                PlacementResolverRegistry::instance()
            );
        });
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

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
