<?php

namespace Elemacy\Modules\CustomCss;

defined('ABSPATH') || exit;

use Elemacy\Core\Module;

class CustomCss extends Module
{
    public function get_name(): string
    {
        return 'custom-css';
    }

    public function get_title(): string
    {
        return __('Custom CSS', 'elemacy');
    }

    public function get_icon(): string
    {
        return 'file-cog';
    }

    public function get_description(): string
    {
        return __('Add custom CSS controls to Elementor elements and pages.', 'elemacy');
    }

    public function is_headless(): bool
    {
        return true;
    }

    public function get_providers(): array
    {
        return [new CustomCssServiceProvider()];
    }

    public function init(): void
    {
    }
}
