<?php

namespace Elemacy\Forms;

use Elemacy\Core\RequestState;
use Elemacy\Forms\Http\SubmissionController;
use Elemacy\Forms\Rendering\FormRenderer;
use Elemacy\Http\AjaxRouter;

defined('ABSPATH') || exit;

/**
 * Attaches the forms engine: the public submission endpoint, the shared
 * front-end assets every form builder depends on, and the per-request
 * render state. The registries load only when a form is rendered, edited or
 * submitted.
 */
final class FormsBootstrap
{
    public const ASSET_HANDLE = 'elemacy-form';

    private bool $hooked = false;

    public function register_hooks(): void
    {
        if ($this->hooked) {
            return;
        }

        $this->hooked = true;

        AjaxRouter::add_action(SubmissionController::ACTION, [SubmissionController::class, 'index'])->for_public();
        RequestState::register(FormRenderer::class, [FormRenderer::class, 'reset']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    /** Registered, not enqueued: a form's builder pulls the handles in as dependencies. */
    public function register_assets(): void
    {
        wp_register_style(self::ASSET_HANDLE, ELEMACY_URL . 'assets/frontend/css/form.css', ['elemacy-core'], ELEMACY_VERSION);
        wp_register_script(self::ASSET_HANDLE, ELEMACY_URL . 'assets/frontend/js/form.js', [], ELEMACY_VERSION, true);
        wp_add_inline_script(self::ASSET_HANDLE, 'window.elemacyFormsConfig = ' . wp_json_encode(self::config()) . ';', 'before');
    }

    /** The window.elemacyFormsConfig data. The nonce exists only for a logged-in visitor. */
    public static function config(): array
    {
        $config = [
            'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
            'i18n' => [
                'required' => __('This field is required.', 'elemacy'),
                'error' => __('An error occurred. Please try again.', 'elemacy'),
                'network' => __('The form could not be sent. Check your connection and try again.', 'elemacy'),
            ],
        ];

        if (is_user_logged_in()) {
            $config['nonce'] = wp_create_nonce(SubmissionController::NONCE_ACTION);
        }

        return $config;
    }
}
