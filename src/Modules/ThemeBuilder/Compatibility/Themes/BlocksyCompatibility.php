<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

class BlocksyCompatibility implements ThemeCompatibilityInterface
{
    public function register(ThemeBuilderManager $manager): void
    {
        if ($manager->get_header_id()) {
            add_filter('blocksy:builder:header:enabled', '__return_false');
            add_action('blocksy:header:before', [$manager, 'render_header'], 5);
        }

        if ($manager->get_footer_id()) {
            add_filter('blocksy:builder:footer:enabled', '__return_false');
            add_action('blocksy:footer:before', [$manager, 'render_footer'], 5);
        }
    }
}

