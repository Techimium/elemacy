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

        // The filter only judges the theme's own check of each side, so a side
        // is marked only as late as that check: the header after everything
        // hooked to wp_body_open, the footer as get_footer fires. Marking the
        // header at get_header would also reach Hello's one-time reading of
        // the Elementor kit during wp_head, which would then register no
        // header/footer settings and leave the native footer empty.
        add_action('wp_body_open', function () {
            $this->current = 'header';
        }, PHP_INT_MAX);
        add_action('get_footer', function () use ($manager, $has_footer) {
            $this->current = 'footer';
            if ($has_footer) {
                $manager->render_footer();
            }
        }, 1);

        if ($has_header) {
            add_action('wp_body_open', [$manager, 'render_header'], 5);
        }
    }
}
