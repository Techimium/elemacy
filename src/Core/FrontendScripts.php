<?php

namespace Elemacy\Core;

if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Support\Utils;

/**
 * Registers (never enqueues) the shared frontend handles. Widgets and the popup
 * engine declare `elemacy-frontend` / `elemacy-core` as dependencies, so the
 * assets — and the localized `elemacy` config — load only on pages that
 * actually render something of ours.
 *
 * `elemacy-elementor-lifecycle` (design.md D9, task 9.5) is the one shared
 * Elementor frontend lifecycle adapter for markup inserted outside
 * Elementor's own render pipeline; the popup engine and Loop Grid's AJAX
 * pagination script both declare it as a dependency rather than each
 * duplicating its widget-handler-initialization logic inline.
 */
class FrontendScripts
{
    protected bool $registered = false;

    /**
     * Attaches this class's WordPress hooks. Idempotent: a second call does
     * nothing (design.md D7 — constructors store dependencies, they do not
     * register hooks; this is where that happens instead).
     */
    public function register_hooks(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        add_action('wp_enqueue_scripts', [$this, 'register']);
    }

    public function register()
    {
        $this->register_scripts();
        $this->register_styles();
    }

    protected function register_scripts()
    {
        wp_register_script('elemacy-frontend', ELEMACY_URL . 'assets/frontend/js/core.js', [], ELEMACY_VERSION, true);

        wp_register_script(
            'elemacy-elementor-lifecycle',
            ELEMACY_URL . 'assets/frontend/js/elementor-lifecycle.js',
            ['jquery'],
            ELEMACY_VERSION,
            true
        );

        // Attached at registration: prints whenever the handle is pulled in as
        // a dependency, not only on a direct enqueue.
        wp_localize_script('elemacy-frontend', 'elemacy', apply_filters(Hooks::FRONTEND_SCRIPT_DATA_FILTER, [
            'ajax_url' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce' => wp_create_nonce(Utils::with_prefix('ajax_nonce')),
        ]));
    }

    protected function register_styles()
    {
        wp_register_style('elemacy-core', ELEMACY_URL . 'assets/frontend/css/core.css', [], ELEMACY_VERSION);
    }
}
