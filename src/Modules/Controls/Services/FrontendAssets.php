<?php

namespace Elemacy\Modules\Controls\Services;

use Elemacy\Support\Utils;

if (!defined('ABSPATH')) {
    exit;
}

class FrontendAssets
{
    public function __construct()
    {
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'register_assets']);
    }

    /**
     * Register (not necessarily enqueue) frontend styles/scripts.
     * Widgets can declare dependencies by handle to keep loading tight and conflict‑free.
     */
    public function register_assets(): void
    {
        wp_enqueue_script(
            'elemacy-custom-css-editor',
            Utils::get_plugin_url('src/Modules/Controls/assets/scripts/editor.js'),
            ['jquery', 'elementor-editor'],
            ELEMACY_VERSION,
            true
        );
    }
}

