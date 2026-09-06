<?php

namespace Elemacy\Modules\Widgets\Services;

use Elemacy\Support\Brand;
use Elemacy\Support\Utils;

if (!defined('ABSPATH')) {
    exit;
}

class EditorAssets
{
    public function __construct()
    {
        add_action('elementor/editor/after_enqueue_styles', [$this, 'enqueue_editor_styles']);
    }

    public function enqueue_editor_styles(): void
    {
        wp_enqueue_style(
            'elemacy-widgets-editor',
            Utils::get_plugin_url('src/Modules/Widgets/assets/styles/editor.css'),
            [],
            ELEMACY_VERSION
        );
        wp_add_inline_style(
            'elemacy-widgets-editor',
            ':root { --elemacy-brand-mark: url("' . Brand::data_uri() . '"); }'
        );
    }
}
