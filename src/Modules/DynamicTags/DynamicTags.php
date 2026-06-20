<?php

namespace Elemacy\Modules\DynamicTags;

defined('ABSPATH') || exit;

use Elemacy\Core\Module;
use Elemacy\Modules\DynamicTags\Services\TagManager;

class DynamicTags extends Module
{
    public function get_name(): string
    {
        return 'dynamic-tags';
    }

    public function get_title(): string
    {
        return __('Dynamic Tags', 'elemacy');
    }

    public function get_icon(): string
    {
        return 'plug';
    }

    public function get_description(): string
    {
        return __('Pull live post, site, author, archive and WooCommerce data into any Elementor element.', 'elemacy');
    }

    public function is_headless(): bool
    {
        return true;
    }

    public function init(): void
    {
        TagManager::init();
    }
}
