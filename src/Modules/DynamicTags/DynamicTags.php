<?php

namespace Elemacy\Modules\DynamicTags;

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
        return __('Additional dynamic tags for Elementor.', 'elemacy');
    }

    public function get_dependencies(): array
    {
        return [];
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
