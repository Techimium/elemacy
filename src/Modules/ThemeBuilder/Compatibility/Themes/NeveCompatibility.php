<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

class NeveCompatibility implements ThemeCompatibilityInterface
{
    public function register(ThemeBuilderManager $manager): void
    {
        if ($manager->get_header_id()) {
            add_action('template_redirect', function () use ($manager) {
                remove_all_actions('neve_do_header');
                remove_all_actions('neve_do_top_bar');
                add_action('neve_do_header', [$manager, 'render_header'], 0);
            }, 10);
        }

        if ($manager->get_footer_id()) {
            add_action('template_redirect', function () use ($manager) {
                remove_all_actions('neve_do_footer');
                add_action('neve_do_footer', [$manager, 'render_footer'], 0);
            }, 10);
        }
    }
}

