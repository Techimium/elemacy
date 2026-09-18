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
        // AJAX-loaded pagination content re-initializes Elementor widgets
        // through the shared lifecycle adapter
        // instead of duplicating that logic inline.
        $this->register_script('loop-grid', ['elemacy-elementor-lifecycle']);

        $this->register_style('loop-carousel');
        $this->register_script('loop-carousel');

        $this->register_style('form');
        $this->register_script('form');

        $this->register_style('search');
        $this->register_script('search');

        $this->register_script('acf-accordion');
    }

    protected function register_script($file_name, array $extra_deps = [])
    {
        wp_register_script(
            'elemacy-' . $file_name,
            Utils::get_plugin_url('src/Modules/Widgets/assets/scripts/' . $file_name . '.js'),
            array_merge(['jquery', 'elemacy-frontend'], $extra_deps),
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

