<?php

namespace Elemacy\Modules\Controls\Services;

defined('ABSPATH') || exit;

class FrontendAssets
{
    protected ControlRegistry $registry;

    public function __construct(ControlRegistry $registry)
    {
        $this->registry = $registry;

        add_action('elementor/editor/after_enqueue_scripts', [$this, 'register_assets']);
    }

    public function register_assets(): void
    {
        foreach ($this->registry->all() as $control) {
            foreach ($control->get_editor_assets() as $asset) {
                if (empty($asset['handle']) || empty($asset['src'])) {
                    continue;
                }

                wp_enqueue_script(
                    $asset['handle'],
                    $asset['src'],
                    $asset['deps'] ?? [],
                    $asset['version'] ?? ELEMACY_VERSION,
                    $asset['in_footer'] ?? true
                );
            }
        }
    }
}
