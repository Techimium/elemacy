<?php

namespace Elemacy\Core;

if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\Elemacy;
use Elemacy\Core\Hooks;
use Elemacy\Support\Utils;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

class AdminScripts
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_global_assets']);

        if (!Utils::is_plugin_page()) {
            return;
        }

        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('script_loader_tag', [$this, 'update_script_type'], 10, 3);
    }

    public function enqueue_global_assets()
    {
        wp_enqueue_style(
            'elemacy-admin-global',
            ELEMACY_URL . 'assets/admin-extras/admin-global.css',
            [],
            ELEMACY_VERSION
        );
        wp_enqueue_script(
            'elemacy-admin-global',
            ELEMACY_URL . 'assets/admin-extras/admin-global.js',
            [],
            ELEMACY_VERSION,
            true
        );
    }

    public function enqueue()
    {
        $handle = 'elemacy-admin-app';

        if (ELEMACY_ENV === 'dev') {
            $this->enqueue_dev_scripts();
        } else {
            $this->enqueue_production_scripts();
        }

        $script_data = apply_filters(Hooks::ADMIN_SCRIPT_DATA_FILTER, [
            'api_base' => esc_url_raw(rest_url()) . 'elemacy/',
            'nonce' => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url(),
            'templateTypes' => ThemeBuilderManager::instance()->get_available_template_types(),
            'modules' => Elemacy::get_instance()->get_module_manager()->to_array(),
        ]);

        wp_localize_script($handle, 'elemacy', $script_data);

        $this->enqueue_sidebar_sync_script();

        do_action(Hooks::ENQUEUE_ADMIN_SCRIPTS_ACTION);
    }

    protected function enqueue_sidebar_sync_script()
    {
        wp_register_script(
            'elemacy-admin-sidebar-sync',
            ELEMACY_URL . 'assets/admin-extras/sidebar-sync.js',
            [],
            ELEMACY_VERSION,
            true
        );
        wp_enqueue_script('elemacy-admin-sidebar-sync');
    }

    public function enqueue_production_scripts()
    {
        wp_register_style('elemacy-admin-app', ELEMACY_URL . 'assets/admin/styles/admin.css', [], ELEMACY_VERSION);
        wp_enqueue_style('elemacy-admin-app');

        wp_register_script('elemacy-admin-app', ELEMACY_URL . 'assets/admin/scripts/admin.js', ['wp-i18n'], ELEMACY_VERSION, true);
        wp_enqueue_script('elemacy-admin-app');
        wp_set_script_translations('elemacy-admin-app', 'elemacy', ELEMACY_PATH . 'languages');
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

        // App entry (e.g. main.jsx)
        wp_enqueue_script(
            'elemacy-admin-app',
            'http://localhost:5173/src/main.tsx',
            ['elemacy-vite-client', 'wp-i18n'],
            null,
            true
        );
        wp_set_script_translations('elemacy-admin-app', 'elemacy', ELEMACY_PATH . 'languages');

        // React Refresh preamble as inline module
        $preamble = '
            import RefreshRuntime from "http://localhost:5173/@react-refresh";
            RefreshRuntime.injectIntoGlobalHook(window);
            window.$RefreshReg$ = () => {};
            window.$RefreshSig$ = () => (type) => type;
            window.__vite_plugin_react_preamble_installed__ = true;
        ';

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
