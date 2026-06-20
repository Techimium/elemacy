<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

/**
 * Renders a clean, actionable admin notice for a missing/inactive plugin
 * dependency: a persistent card with an Install or Activate call to action
 * instead of a self-deactivating one-shot error, so the user can resolve the
 * dependency without leaving the page.
 */
class DependencyNotice
{
    private string $slug;
    private string $basename;
    private string $plugin_label;

    public function __construct(string $slug, string $basename, string $plugin_label)
    {
        $this->slug         = $slug;
        $this->basename     = $basename;
        $this->plugin_label = $plugin_label;
    }

    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('admin_notices', [$this, 'render']);
    }

    public function enqueue_styles(): void
    {
        wp_enqueue_style(
            'elemacy-dependency-notice',
            ELEMACY_URL . 'assets/admin/styles/dependency-notice.css',
            [],
            ELEMACY_VERSION
        );
    }

    public function render(): void
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        $is_installed = $this->is_installed();
        $can_act      = $is_installed
            ? current_user_can('activate_plugin', $this->basename)
            : current_user_can('install_plugins');

        $this->view([
            'title' => sprintf(
                /* translators: %s: dependency plugin name. */
                __('Elemacy requires %s', 'elemacy'),
                $this->plugin_label
            ),
            'message' => $is_installed
                ? sprintf(
                    /* translators: %s: dependency plugin name. */
                    __('Elemacy needs %s to run. It is installed but not active — activate it to get started.', 'elemacy'),
                    $this->plugin_label
                )
                : sprintf(
                    /* translators: %s: dependency plugin name. */
                    __('Elemacy needs %s to run. Install it to get started.', 'elemacy'),
                    $this->plugin_label
                ),
            'action_url' => $can_act
                ? ($is_installed ? $this->activate_url() : $this->install_url())
                : null,
            'button_label' => $is_installed
                ? sprintf(
                    /* translators: %s: dependency plugin name. */
                    __('Activate %s', 'elemacy'),
                    $this->plugin_label
                )
                : sprintf(
                    /* translators: %s: dependency plugin name. */
                    __('Install %s', 'elemacy'),
                    $this->plugin_label
                ),
        ]);
    }

    private function view(array $data): void
    {
        require ELEMACY_PATH . 'src/Core/views/dependency-notice.php';
    }

    private function is_installed(): bool
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return array_key_exists($this->basename, get_plugins());
    }

    private function install_url(): string
    {
        return wp_nonce_url(
            self_admin_url('update.php?action=install-plugin&plugin=' . $this->slug),
            'install-plugin_' . $this->slug
        );
    }

    private function activate_url(): string
    {
        return wp_nonce_url(
            self_admin_url('plugins.php?action=activate&plugin=' . $this->basename),
            'activate-plugin_' . $this->basename
        );
    }
}
