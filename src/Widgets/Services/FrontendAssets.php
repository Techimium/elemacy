<?php

namespace Elemacy\Widgets\Services;

use Elemacy\Widgets\NavMenu\BreakpointStyles;
use Elemacy\Widgets\Support\WidgetAssets;

if (!defined('ABSPATH')) {
    exit;
}

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
     * Registers (does not enqueue) the general widgets' frontend assets.
     * Widgets declare them by handle, so a page loads only what it uses.
     */
    public function register_assets(): void
    {
        WidgetAssets::register_style('src/Widgets/NavMenu', 'nav-menu');
        // Breakpoint rules depend on the site's Elementor breakpoints, so they
        // are generated rather than shipped in the stylesheet.
        wp_add_inline_style('elemacy-nav-menu', BreakpointStyles::css());
        WidgetAssets::register_script('src/Widgets/NavMenu', 'nav-menu');

        WidgetAssets::register_style('src/Widgets/Search', 'search');
        WidgetAssets::register_script('src/Widgets/Search', 'search');

        WidgetAssets::register_script('src/Widgets/AcfAccordion', 'acf-accordion');
    }
}
