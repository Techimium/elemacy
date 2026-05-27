<?php

namespace Elemacy\Placeholders;

defined('ABSPATH') || exit;

use Elemacy\Core\Module;

class AnimationsPlaceholder extends Module
{
    public function get_name(): string
    {
        return 'animations';
    }

    public function get_title(): string
    {
        return __('Animations', 'elemacy');
    }

    public function get_icon(): string
    {
        return 'animation';
    }

    public function get_description(): string
    {
        return __('Add scroll, hover and entrance animations to any Elementor element with no code.', 'elemacy');
    }

    public function get_badge(): ?string
    {
        return __('Pro', 'elemacy');
    }

    public function get_url(): ?string
    {
        return 'https://elemacy.com';
    }

    public function is_placeholder(): bool
    {
        return true;
    }

    public function is_headless(): bool
    {
        return true;
    }

    public function init() {}
}
