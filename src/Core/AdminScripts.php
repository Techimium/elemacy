<?php

namespace Elemacy\Core;

use Elemacy\Support\Utils;

class AdminScripts
{
    public function __construct()
    {
        if (!Utils::is_plugin_page()) {
            return;
        }

        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('script_loader_tag', [$this, 'update_script_type'], 10, 3);
    }

    public function enqueue()
    {
        $handle = 'elemacy-core';
        if (ELEMACY_ENV === 'dev') {
            $this->enqueue_dev_scripts();
            $handle = 'elemacy-admin-app';
        } else {
            $this->enqueue_production_scripts();
        }

        wp_localize_script($handle, 'elemacy', [
            'api_base' => esc_url_raw(rest_url()) . 'elemacy/',
            'nonce' => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url(),
        ]);
    }

    public function enqueue_production_scripts()
    {
        wp_register_script('elemacy-core', ELEMACY_PATH . '/assets/admin/scripts/admin.js', [], ELEMACY_VERSION, true);
        wp_enqueue_script('elemacy-core');
    }

    public function enqueue_dev_scripts()
    {
        // Vite client (HMR websocket)
        wp_enqueue_script(
            'elemacy-vite-client',
            'http://localhost:5173/@vite/client',
            [],
            null,
            true
        );

        // Your app entry (e.g. main.jsx)
        wp_enqueue_script(
            'elemacy-admin-app',
            'http://localhost:5173/src/main.jsx',
            ['elemacy-vite-client'],
            null,
            true
        );

        // React Refresh preamble as inline module
        $preamble = <<<JS
            import RefreshRuntime from "http://localhost:5173/@react-refresh";
            RefreshRuntime.injectIntoGlobalHook(window);
            window.\$RefreshReg\$ = () => {};
            window.\$RefreshSig\$ = () => (type) => type;
            window.__vite_plugin_react_preamble_installed__ = true;
        JS;

        wp_add_inline_script('elemacy-admin-app', $preamble, 'before');
    }

    public function update_script_type($tag, $handle, $src)
    {
        if (in_array($handle, ['elemacy-vite-client', 'elemacy-admin-app'], true)) {
            $tag = str_replace('<script', '<script type="module"', $tag);
        }

        return $tag;
    }
}
