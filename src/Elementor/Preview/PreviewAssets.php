<?php

namespace Elemacy\Elementor\Preview;

defined('ABSPATH') || exit;

use Elemacy\Elementor\Documents\PreviewablePageBase;
use Elemacy\Support\Utils;
use Elementor\Plugin;

/**
 * Enqueues the editor preview bridge for any previewable document, so the
 * "Apply & Preview" button works the same across Loop Item, Single, Archive and
 * Search templates.
 */
class PreviewAssets
{
    public const RELATIVE_PATH = 'assets/editor/preview-bridge.js';

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

        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(): void
    {
        if (!$this->is_previewable_document()) {
            return;
        }

        $file = Utils::get_plugin_path(self::RELATIVE_PATH);
        $version = is_readable($file) && filemtime($file) ? (string) filemtime($file) : ELEMACY_VERSION;

        wp_enqueue_script(
            'elemacy-preview-bridge',
            Utils::get_plugin_url(self::RELATIVE_PATH),
            ['jquery'],
            $version,
            true
        );
    }

    protected function is_previewable_document(): bool
    {
        $post_id = (int) Plugin::instance()->editor->get_post_id();

        if ($post_id <= 0) {
            return false;
        }

        return Plugin::instance()->documents->get($post_id) instanceof PreviewablePageBase;
    }
}
