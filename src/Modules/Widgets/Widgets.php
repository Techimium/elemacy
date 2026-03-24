<?php

namespace Elemacy\Modules\Widgets;

use Elemacy\Core\Module;
use Elemacy\Modules\Widgets\Services\FrontendAssets;
use Elemacy\Modules\Widgets\Services\WidgetManager;

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

    public function get_description(): string
    {
        return __('Custom Elementor widgets for Elemacy.', 'elemacy');
    }

    public function get_dependencies(): array
    {
        return [];
    }

    public function is_always_active(): bool
    {
        return true;
    }

    public function init(): void
    {
        new FrontendAssets();
        WidgetManager::instance();
        require_once __DIR__ . '/Config/ajax.php';
    }

    public function register_routes()
    {
        require_once __DIR__ . '/Config/api.php';
    }
}
