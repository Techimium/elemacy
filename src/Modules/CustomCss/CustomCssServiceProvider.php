<?php

namespace Elemacy\Modules\CustomCss;

defined('ABSPATH') || exit;

use Elemacy\Core\Container;
use Elemacy\Core\Contracts\ServiceProviderInterface;
use Elemacy\Modules\CustomCss\Services\CustomCssManager;
use Elemacy\Modules\CustomCss\Services\FrontendAssets;

/**
 * The collaborators CustomCss::init() used to construct directly.
 */
class CustomCssServiceProvider implements ServiceProviderInterface
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

        new FrontendAssets();
        new CustomCssManager();
    }
}
