<?php

namespace Elemacy\Modules\Widgets;

defined('ABSPATH') || exit;

use Elemacy\Core\Container;
use Elemacy\Core\Contracts\ServiceProviderInterface;
use Elemacy\Modules\Widgets\Documents\DocumentManager;
use Elemacy\Modules\Widgets\Services\EditorAssets;
use Elemacy\Modules\Widgets\Services\FrontendAssets;
use Elemacy\Modules\Widgets\Services\WidgetManager;

/**
 * The collaborators Widgets::init() used to construct directly.
 */
class WidgetsServiceProvider implements ServiceProviderInterface
{
    protected bool $booted = false;

    public function register(Container $container): void
    {
        $container->bind(\Elemacy\Modules\Forms\Services\PublishedFormDefinitionResolver::class, static function () {
            return new \Elemacy\Modules\Forms\Services\PublishedFormDefinitionResolver(
                \Elemacy\TemplateLibrary\TypeRegistry::instance(),
                \Elemacy\Modules\Widgets\Services\PlacementResolverRegistry::instance()
            );
        });
        $container->bind(\Elemacy\Modules\Forms\Services\SubmissionService::class, static function (Container $container) {
            return new \Elemacy\Modules\Forms\Services\SubmissionService(
                $container->make(\Elemacy\Modules\Forms\Services\PublishedFormDefinitionResolver::class),
                $container->make(\Elemacy\Modules\Forms\Services\SubmissionValidator::class),
                $container->make(\Elemacy\Modules\Forms\Services\SubmissionRateLimiter::class),
                [$container->make(\Elemacy\Modules\Forms\Actions\EmailAction::class)]
            );
        });
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        new DocumentManager();
        new FrontendAssets();
        new EditorAssets();
        WidgetManager::instance();
    }
}
