<?php

namespace Elemacy\Modules\Widgets\Services;

use Elemacy\Support\Utils;

if (!defined('ABSPATH')) {
    exit;
}

class FrontendAssets
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    /**
     * Register (not necessarily enqueue) frontend styles/scripts.
     * Widgets can declare dependencies by handle to keep loading tight and conflict‑free.
     */
    public function register_assets(): void
    {
        wp_register_style(
            'elemacy-nav-menu',
            Utils::get_plugin_url('src/Modules/Widgets/assets/styles/nav-menu.css'),
            [],
            ELEMACY_VERSION
        );

        wp_register_script(
            'elemacy-nav-menu',
            Utils::get_plugin_url('src/Modules/Widgets/assets/scripts/nav-menu.js'),
            ['jquery'],
            ELEMACY_VERSION,
            true
        );

        wp_register_style(
            'elemacy-loop-builder',
            Utils::get_plugin_url('src/Modules/Widgets/assets/styles/loop-builder.css'),
            [],
            ELEMACY_VERSION
        );

        wp_register_script(
            'elemacy-loop-builder',
            Utils::get_plugin_url('src/Modules/Widgets/assets/scripts/loop-builder.js'),
            ['jquery'],
            ELEMACY_VERSION,
            true
        );
    }
}

