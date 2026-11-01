<?php

namespace Elemacy\Modules\ThemeBuilder\Contracts;

defined('ABSPATH') || exit;

/**
 * What a theme compatibility adapter needs from the Theme Builder: whether a
 * side's native chrome is replaced, and rendering the replacement.
 *
 * @since 1.0.0
 * @api
 */
interface ThemeChromeInterface
{
    /**
     * Whether the active theme's own header or footer is replaced on this
     * request. Every adapter asks this, and only this, so the
     * THEME_BUILDER_SUPPRESS_NATIVE_CHROME_FILTER opt-out reaches all of them.
     *
     * @param string $side 'header' or 'footer'.
     */
    public function replaces_native_chrome(string $side): bool;

    /**
     * Declares that the theme wraps the $side template in its own
     * `<header>`/`<footer>` element, so the template does not render a second
     * landmark of the same kind.
     *
     * @param string $side 'header' or 'footer'.
     */
    public function keep_theme_landmark(string $side): void;

    /** Renders the header template content, at most once per request. */
    public function render_header(): void;

    /** Renders the footer template content, at most once per request. */
    public function render_footer(): void;
}
