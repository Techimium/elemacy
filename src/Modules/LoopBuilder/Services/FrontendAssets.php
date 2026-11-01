<?php

namespace Elemacy\Modules\LoopBuilder\Services;

defined('ABSPATH') || exit;

use Elemacy\Widgets\Support\WidgetAssets;

/**
 * @internal
 */
class FrontendAssets
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    /**
     * Registers (does not enqueue) the loop widgets' frontend assets. Widgets
     * declare them by handle, so a page loads only what it uses.
     */
    public function register_assets(): void
    {
        WidgetAssets::register_style('src/Modules/LoopBuilder/Widgets/LoopGrid', 'loop-grid');
        // AJAX-loaded pagination content re-initializes Elementor widgets
        // through the shared lifecycle adapter instead of duplicating that
        // logic inline.
        WidgetAssets::register_script('src/Modules/LoopBuilder/Widgets/LoopGrid', 'loop-grid', ['elemacy-elementor-lifecycle']);
        // Load More / Infinite Scroll append pages through the same adapter.
        WidgetAssets::register_script('src/Modules/LoopBuilder/Widgets/LoopGrid', 'loop-incremental', ['elemacy-elementor-lifecycle']);

        WidgetAssets::register_style('src/Modules/LoopBuilder/Widgets/LoopCarousel', 'loop-carousel');
        WidgetAssets::register_script('src/Modules/LoopBuilder/Widgets/LoopCarousel', 'loop-carousel');
    }
}
