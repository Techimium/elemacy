<?php

namespace Elemacy\Modules\ThemeBuilder;

use Elemacy\Core\Module;
use Elemacy\Modules\ThemeBuilder\PostTypes\TemplatePostType;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

class ThemeBuilder extends Module
{
    public function get_name(): string
    {
        return 'theme-builder';
    }

    public function get_title(): string
    {
        return __('Theme Builder', 'elemacy');
    }

    public function get_description(): string
    {
        return __('Manage your site structure templates for a full theme experience.', 'elemacy');
    }

    public function get_dependencies(): array
    {
        return [];
    }

    public function init(): void
    {
        TemplatePostType::register();
        ThemeBuilderManager::instance()->register_hooks();
    }

    public function register_routes()
    {
        require_once __DIR__ . '/Config/api.php';
    }
}