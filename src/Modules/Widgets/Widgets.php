<?php

namespace Elemacy\Modules\Widgets;

use Elemacy\Core\Module;
use Elemacy\Modules\Widgets\Services\FrontendAssets;
use Elemacy\Modules\Widgets\Services\WidgetManager;
use Elemacy\Support\Utils;

class Widgets extends Module
{
    public function get_name(): string
    {
        return 'widgets';
    }

    public function get_title(): string
    {
        return __('Widgets', 'elemacy');
    }

    public function get_icon(): string
    {
        return 'component';
    }

    public function get_description(): string
    {
        return __('Custom Elementor widgets to enhance your website.', 'elemacy');
    }

    public function get_dependencies(): array
    {
        return [];
    }

    public function init(): void
    {
        new FrontendAssets();
        WidgetManager::instance();
        require_once Utils::get_plugin_path('src/Modules/Widgets/Config/ajax.php');
    }

    public function register_routes()
    {
        require_once Utils::get_plugin_path('src/Modules/Widgets/Config/api.php');
    }
}
