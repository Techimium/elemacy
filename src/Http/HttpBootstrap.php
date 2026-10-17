<?php

namespace Elemacy\Http;

defined('ABSPATH') || exit;

/**
 * Wires the plugin's REST and AJAX transport: loads Core's own route
 * definitions and registers every defined route with WordPress once all
 * modules and add-ons have defined theirs.
 */
class HttpBootstrap
{
    protected bool $registered = false;

    /**
     * Attaches this class's WordPress hooks. Idempotent: a second call does
     * nothing.
     */
    public function register_hooks(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        require_once ELEMACY_PATH . 'src/Config/api.php';

        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('init', [AjaxRouter::class, 'register']);
    }

    public function register_rest_routes(): void
    {
        foreach (Route::get_routes() as $route) {
            $route->register();
        }
    }
}
