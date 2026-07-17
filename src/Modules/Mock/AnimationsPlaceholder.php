<?php

namespace Elemacy\Modules\Mock;

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
        return __('Add polished entrance animations to any Elementor element, triggered on scroll or page load — no code required.', 'elemacy');
    }

    public function get_badge(): ?string
    {
        return __('Pro', 'elemacy');
    }

    public function get_url(): ?string
    {
        return 'https://elemacy.com';
    }

    public function is_mock(): bool
    {
        return true;
    }

    public function is_headless(): bool
    {
        return true;
    }

    public function init() {}
}
