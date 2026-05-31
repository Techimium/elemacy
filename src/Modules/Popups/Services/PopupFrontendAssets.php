<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Support\Utils;

/**
 * Registers (but does not enqueue) the popup frontend engine script/style.
 *
 * The PopupManager decides whether to actually enqueue them on a given request.
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
            Utils::get_plugin_url('src/Modules/Popups/assets/scripts/engine.js'),
            ['jquery', 'elemacy-frontend'],
            $this->asset_version('src/Modules/Popups/assets/scripts/engine.js'),
            true
        );

        wp_register_style(
            'elemacy-popups',
            Utils::get_plugin_url('src/Modules/Popups/assets/styles/popups.css'),
            ['elemacy-core'],
            $this->asset_version('src/Modules/Popups/assets/styles/popups.css')
        );
    }

    /**
     * Version a frontend asset by its file modification time so edits bust the
     * browser cache automatically, falling back to the plugin version.
     *
     * @param string $relative_path
     * @return string
     */
    protected function asset_version(string $relative_path): string
    {
        $file = Utils::get_plugin_path($relative_path);
        $mtime = is_readable($file) ? filemtime($file) : false;

        return $mtime ? (string) $mtime : ELEMACY_VERSION;
    }
}
