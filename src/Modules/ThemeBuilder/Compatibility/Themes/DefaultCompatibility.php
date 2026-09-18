<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

/** Hello Elementor compatibility. See docs/ARCHITECTURE.md § Rationale digest. */
class DefaultCompatibility implements ThemeCompatibilityInterface
{
    /** @var string|null 'header'|'footer'|null — which side is currently loading. */
    private $current = null;

    public function register(ThemeBuilderManager $manager): void
    {
        $has_header = (bool) $manager->get_header_id();
        $has_footer = (bool) $manager->get_footer_id();

        if (!$has_header && !$has_footer) {
            return;
        }

        add_filter('hello_elementor_header_footer', function ($display) use ($has_header, $has_footer) {
            if ('header' === $this->current) {
                return $has_header ? false : $display;
            }
            if ('footer' === $this->current) {
                return $has_footer ? false : $display;
            }

            return $display;
        });

        if ($has_header) {
            add_action('get_header', function () {
                $this->current = 'header';
            }, 1);
            add_action('wp_body_open', [$manager, 'render_header'], 5);
        }

        if ($has_footer) {
            add_action('get_footer', function () use ($manager) {
                $this->current = 'footer';
                $manager->render_footer();
            }, 1);
        }
    }
}
