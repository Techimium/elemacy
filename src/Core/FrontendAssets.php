<?php

namespace Elemacy\Core;

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
        // Nav menu styles.
        wp_register_style(
            'elemacy-nav-menu',
            ELEMACY_URL . 'assets/frontend/styles/nav-menu.css',
            [],
            ELEMACY_VERSION
        );

        // Nav menu script (only behavior for Elemacy nav widgets; no globals).
        wp_register_script(
            'elemacy-nav-menu',
            ELEMACY_URL . 'assets/frontend/scripts/nav-menu.js',
            [ 'jquery' ],
            ELEMACY_VERSION,
            true
        );
    }
}

