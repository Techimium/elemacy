<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elemacy\Modules\ThemeBuilder\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

/** Global compatibility. See docs/ARCHITECTURE.md § Rationale digest. */
class GlobalCompatibility implements ThemeCompatibilityInterface
{
    public function register(ThemeBuilderManager $manager): void
    {
        $has_header = (bool) $manager->get_header_id();
        $has_footer = (bool) $manager->get_footer_id();

        if ($has_header) {
            // Force-load the theme header.php so we can detect whether the theme
            // calls wp_body_open. If it doesn't, fire it ourselves (once) so the
            // primary injection point below still runs.
            add_action('get_header', function ($name = '') {
                $templates = [];
                $name = (string) $name;
                if ('' !== $name) {
                    $templates[] = "header-{$name}.php";
                }
                $templates[] = 'header.php';

                locate_template($templates, true, true);

                did_action('wp_body_open') || wp_body_open();
            }, 1);

            // Primary injection point.
            add_action('wp_body_open', function () use ($manager) {
                $this->print_stretched_header($manager);
            }, 5);

            /**
             * Fallback hook for themes that don't call wp_body_open.
             * This hook is not triggered automatically; theme adapters (or users)
             * can call it if needed without breaking markup.
             */
            add_action(Hooks::THEME_BUILDER_FALLBACK_HEADER_ACTION, function () use ($manager) {
                $this->print_stretched_header($manager);
            }, 10);
        }

        if ($has_footer) {
            // Fires before locate_template() loads footer.php (and so
            // before its own wp_footer() call), landing Elemacy's footer
            // ahead of the script-printing deadline rather than inside it.
            add_action('get_footer', [$manager, 'render_footer'], 5);
        }

        if ($has_header || $has_footer) {
            add_action('wp_enqueue_scripts', function () use ($has_header, $has_footer) {
                $handle = 'elemacy-themebuilder-compat';
                if (!wp_style_is($handle, 'registered')) {
                    wp_register_style($handle, false, [], ELEMACY_VERSION);
                }
                wp_enqueue_style($handle);

                // Scoped to the document's own near-root structure (direct
                // children of <body>, an optional single wrapper div, or a
                // #page wrapper) so these rules can never reach into
                // Elementor/Elemacy content, which is always nested far
                // deeper than that. Real themes vary in wrapper depth; this
                // covers the common shapes without matching builder markup.
                $native_root = ':is(body, body > div, #page, .hfeed)';
                $css = '
                .elemacy-force-stretched-header {
                    width: 100vw;
                    position: relative;
                    margin-left: -50vw;
                    left: 50%;
                }';

                if ($has_header) {
                    // #page > hr:first-of-type is the deprecated theme-compat
                    // header.php's own trailing divider, immediately after #header.
                    $css .= '
                    ' . $native_root . ' > header#masthead,
                    ' . $native_root . ' > .site-header,
                    #page > #header,
                    #page > hr:first-of-type {
                        display: none;
                    }';
                }

                if ($has_footer) {
                    // #page > hr:last-of-type is the deprecated theme-compat
                    // footer.php's own leading divider, immediately before #footer.
                    $css .= '
                    ' . $native_root . ' > footer#colophon,
                    ' . $native_root . ' > .site-footer,
                    #page > #footer,
                    #page > hr:last-of-type {
                        display: none;
                    }';
                }

                wp_add_inline_style($handle, $css);
            }, 50);
        }
    }

    /**
     * Prints the stretched wrapper only around a header that actually rendered:
     * the wrapper is 100vw wide, so an empty one (Canvas pages render no header,
     * and a second request for an already-rendered header prints nothing) would
     * leave a full-width element in the page.
     */
    private function print_stretched_header(ThemeBuilderManager $manager): void
    {
        ob_start();
        $manager->render_header();
        $header = (string) ob_get_clean();

        if ('' !== $header) {
            // PHPCS - already escaped by Elementor.
            echo '<div class="elemacy-force-stretched-header">' . $header . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
