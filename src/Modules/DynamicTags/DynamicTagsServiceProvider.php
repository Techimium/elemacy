<?php

namespace Elemacy\Modules\DynamicTags;

defined('ABSPATH') || exit;

use Elemacy\Core\Container;
use Elemacy\Core\Contracts\ServiceProviderInterface;
use Elemacy\Modules\DynamicTags\Services\TagManager;

/**
 * The collaborator DynamicTags::init() used to construct directly.
 */
class DynamicTagsServiceProvider implements ServiceProviderInterface
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

        TagManager::init();
    }
}
