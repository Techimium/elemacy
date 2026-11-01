<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Support\Utils;

/**
 * Registers (but does not enqueue) the popup frontend engine script/style.
 *
 * @internal
 */
class PopupFrontendAssets
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    public function register_assets(): void
    {
        wp_register_script(
            'elemacy-popups-engine',
            Utils::get_plugin_url('src/Modules/Popups/assets/js/engine.js'),
            ['jquery', 'elemacy-frontend', 'elemacy-elementor-lifecycle'],
            ELEMACY_VERSION,
            true
        );

        wp_register_style(
            'elemacy-popups',
            Utils::get_plugin_url('src/Modules/Popups/assets/css/popups.css'),
            ['elemacy-core'],
            ELEMACY_VERSION
        );
    }
}
