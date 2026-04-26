<?php

namespace Elemacy\Modules\Controls;

defined('ABSPATH') || exit;

use Elemacy\Core\Module;
use Elemacy\Modules\Controls\Services\CustomCssManager;
use Elemacy\Modules\Controls\Services\FrontendAssets;

class Controls extends Module
{
    public function get_name(): string
    {
        return 'controls';
    }

    public function get_title(): string
    {
        return __('Controls', 'elemacy');
    }

    public function get_icon(): string
    {
        return 'file-cog';
    }

    public function get_description(): string
    {
        return __('Additional controls for Elementor.', 'elemacy');
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
        new FrontendAssets();
        new CustomCssManager();
    }
}
