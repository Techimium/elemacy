<?php

namespace Elemacy\Core;

class FrontendScripts
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue()
    {
        wp_register_script('elemacy-frontend', ELEMACY_URL . 'assets/frontend/js/core.js', [], ELEMACY_VERSION, true);
        wp_enqueue_script('elemacy-frontend');

        wp_localize_script('elemacy-frontend', 'elemacy', [
            'ajax_url' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce' => wp_create_nonce('elemacy_ajax_nonce'),
        ]);
    }
}
