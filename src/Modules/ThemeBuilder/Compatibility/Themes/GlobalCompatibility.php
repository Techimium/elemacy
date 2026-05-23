<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elemacy\Modules\ThemeBuilder\Compatibility\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

/**
 * Global compatibility.
 *
 * - Inject header via wp_body_open (best-effort across themes).
 * - Inject footer via wp_footer.
 * - Adds minimal CSS to hide common theme header/footer wrappers and force full width.
 *
 * This is intended as a safe fallback when we don't have a theme-specific adapter.
 */
class GlobalCompatibility implements ThemeCompatibilityInterface
{
    public function register(ThemeBuilderManager $manager): void
    {
        $has_header = (bool) $manager->get_header_id();
        $has_footer = (bool) $manager->get_footer_id();

        if ($has_header) {
            // Force-load the theme header.php (require_once) so we can detect whether
            // the theme calls wp_body_open. If it doesn't, we print a fallback header.
            add_action('get_header', function ($name = '') use ($manager) {
                $templates = [];
                $name = (string) $name;
                if ('' !== $name) {
                    $templates[] = "header-{$name}.php";
                }
                $templates[] = 'header.php';

                locate_template($templates, true, true);

                if (!did_action('wp_body_open')) {
                    do_action(Hooks::THEME_BUILDER_FALLBACK_HEADER_ACTION);
                }
            }, 1);

            // Primary injection point.
            add_action('wp_body_open', function () use ($manager) {
                echo '<div class="elemacy-force-stretched-header">';
                $manager->render_header();
                echo '</div>';
            }, 5);

            /**
             * Fallback hook for themes that don't call wp_body_open.
             * This hook is not triggered automatically; theme adapters (or users)
             * can call it if needed without breaking markup.
             */
            add_action(Hooks::THEME_BUILDER_FALLBACK_HEADER_ACTION, function () use ($manager) {
                echo '<div class="elemacy-force-stretched-header">';
                $manager->render_header();
                echo '</div>';
            }, 10);
        }

        if ($has_footer) {
            add_action('wp_footer', [$manager, 'render_footer'], 50);
        }

        if ($has_header || $has_footer) {
            add_action('wp_enqueue_scripts', function () use ($has_header, $has_footer) {
                $handle = 'elemacy-themebuilder-compat';
                if (!wp_style_is($handle, 'registered')) {
                    wp_register_style($handle, false);
                }
                wp_enqueue_style($handle);

                $css = '
                .elemacy-force-stretched-header {
                    width: 100vw;
                    position: relative;
                    margin-left: -50vw;
                    left: 50%;
                }';

                if ($has_header) {
                    $css .= '
                    header#masthead,
                    .site-header {
                        display: none;
                    }';
                }

                if ($has_footer) {
                    $css .= '
                    footer#colophon,
                    .site-footer {
                        display: none;
                    }';
                }

                wp_add_inline_style($handle, $css);
            }, 50);
        }
    }
}

