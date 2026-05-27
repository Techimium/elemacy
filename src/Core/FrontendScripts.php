<?php
namespace Elemacy\Core;

if (!defined('ABSPATH')) {
    exit;
}

class FrontendScripts
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue()
    {
        $this->enqueue_scripts();
        $this->enqueue_styles();
    }

    protected function enqueue_scripts()
    {
        wp_register_script('elemacy-frontend', ELEMACY_URL . 'assets/frontend/js/core.js', [], ELEMACY_VERSION, true);
        wp_enqueue_script('elemacy-frontend');

        wp_localize_script('elemacy-frontend', 'elemacy', [
            'ajax_url' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce' => wp_create_nonce('elemacy_ajax_nonce'),
        ]);
    }

    protected function enqueue_styles()
    {
        wp_register_style('elemacy-core', ELEMACY_URL . 'assets/frontend/css/core.css', [], ELEMACY_VERSION);
        wp_enqueue_style('elemacy-core');
    }
}
