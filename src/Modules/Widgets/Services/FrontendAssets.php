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
        $this->register_style('nav-menu');
        $this->register_script('nav-menu');

        $this->register_style('loop-grid');
        $this->register_script('loop-grid');

        $this->register_style('loop-carousel');
        $this->register_script('loop-carousel');

        $this->register_style('form');
        $this->register_script('form');
    }

    protected function register_script($file_name)
    {
        wp_register_script(
            'elemacy-' . $file_name,
            Utils::get_plugin_url('src/Modules/Widgets/assets/scripts/' . $file_name . '.js'),
            ['jquery', 'elemacy-frontend'],
            ELEMACY_VERSION,
            true
        );
    }

    protected function register_style($file_name)
    {
        wp_register_style(
            'elemacy-' . $file_name,
            Utils::get_plugin_url('src/Modules/Widgets/assets/styles/' . $file_name . '.css'),
            ['elemacy-core'],
            ELEMACY_VERSION
        );
    }
}

