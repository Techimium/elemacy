<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elemacy\Modules\ThemeBuilder\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

/**
 * Generic adapter for any classic theme without a dedicated one. See
 * docs/ARCHITECTURE.md § Native chrome replacement.
 *
 * For each replaced side it prints Elemacy's own document shell, then loads
 * the theme's header.php/footer.php into a discarded buffer. The load is a
 * require_once, so WordPress's own get_header()/get_footer() — which runs
 * right after this — finds the file already loaded and prints nothing: the
 * theme's chrome never reaches the response. Its content template, sidebars
 * and comments are untouched.
 */
class GlobalCompatibility implements ThemeCompatibilityInterface
{
    /**
     * Late, so other get_header/get_footer listeners (Elementor's
     * image-loading output buffer among them) still run before the shell, as
     * they would before the theme's own template.
     */
    private const PRIORITY = 1000;

    /**
     * Shell hooks each side's view runs to completion. They are neutralized
     * only once that has happened, so the discarded native template cannot
     * run any callback a second time — every callback still runs exactly once.
     */
    private const SHELL_HOOKS = [
        'header' => ['wp_head', 'wp_body_open'],
        'footer' => ['wp_footer'],
    ];

    public function register(ThemeBuilderManager $manager): void
    {
        if ($manager->get_header_id() && $this->suppresses('header')) {
            add_action('get_header', function ($name = null, $args = []) {
                $this->replace('header', $name, (array) $args);
            }, self::PRIORITY, 2);
        }

        if ($manager->get_footer_id() && $this->suppresses('footer')) {
            add_action('get_footer', function ($name = null, $args = []) {
                $this->replace('footer', $name, (array) $args);
            }, self::PRIORITY, 2);
        }
    }

    /**
     * Whether native chrome is replaced on $side ('header' or 'footer') for
     * this request. False leaves that side to the theme, as though no
     * template were configured for it.
     */
    private function suppresses(string $side): bool
    {
        return (bool) apply_filters(Hooks::THEME_BUILDER_SUPPRESS_NATIVE_CHROME_FILTER, true, $side);
    }

    /**
     * @param string      $side 'header' or 'footer'.
     * @param string|null $name The name passed to get_header()/get_footer().
     * @param array       $args The args passed to get_header()/get_footer().
     */
    protected function replace(string $side, $name, array $args): void
    {
        $this->print_shell($side);

        foreach (self::SHELL_HOOKS[$side] as $hook) {
            remove_all_actions($hook);
        }

        $templates = [];
        $name = (string) $name;
        if ('' !== $name) {
            $templates[] = "{$side}-{$name}.php";
        }
        $templates[] = "{$side}.php";

        // Also resolves WordPress's deprecated theme-compat stub when the
        // theme has no file of its own, so that stub is discarded too.
        ob_start();
        locate_template($templates, true, true, $args);
        ob_end_clean();
    }

    /**
     * @param string $side 'header' or 'footer'.
     */
    protected function print_shell(string $side): void
    {
        require ELEMACY_PATH . "src/Modules/ThemeBuilder/Views/theme-support-{$side}.php";
    }
}
