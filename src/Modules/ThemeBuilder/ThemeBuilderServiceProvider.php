<?php

namespace Elemacy\Modules\ThemeBuilder;

defined('ABSPATH') || exit;

use Elemacy\Core\Container;
use Elemacy\Core\Contracts\ServiceProviderInterface;
use Elemacy\Modules\ThemeBuilder\Documents\DocumentManager;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

/**
 * The collaborators ThemeBuilder::init() used to construct directly.
 */
class ThemeBuilderServiceProvider implements ServiceProviderInterface
{
    protected bool $booted = false;

    public function register(Container $container): void
    {
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        new DocumentManager();
        ThemeBuilderManager::instance()->register_hooks();
    }
}
