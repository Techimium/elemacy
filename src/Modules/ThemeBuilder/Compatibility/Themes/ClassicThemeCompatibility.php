<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Compatibility\LayoutWrapperReader;
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
 *
 * Layout wrappers that file opened and never closed (or closed without
 * opening) are read from what was discarded and printed around the Elemacy
 * template, so the page stays balanced and keeps the theme's layout. See
 * LayoutWrapperReader.
 */
class ClassicThemeCompatibility implements ThemeCompatibilityInterface
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

    /** @var ThemeBuilderManager */
    private $manager;

    public function register(ThemeBuilderManager $manager): void
    {
        $this->manager = $manager;

        if ($manager->replaces_native_chrome('header')) {
            add_action('get_header', function ($name = null, $args = []) {
                $this->replace('header', $name, (array) $args);
            }, self::PRIORITY, 2);
        }

        if ($manager->replaces_native_chrome('footer')) {
            add_action('get_footer', function ($name = null, $args = []) {
                $this->replace('footer', $name, (array) $args);
            }, self::PRIORITY, 2);
        }
    }

    /**
     * The order matters and is pinned by tests: the shell's hooks run first
     * (so every callback runs once and its output is in the response), then
     * they are emptied, then the theme file is discarded. The Elemacy
     * template and the footer's closing are held back only so the wrappers
     * read from the discarded file can be printed around them.
     *
     * @param string      $side 'header' or 'footer'.
     * @param string|null $name The name passed to get_header()/get_footer().
     * @param array       $args The args passed to get_header()/get_footer().
     */
    protected function replace(string $side, $name, array $args): void
    {
        if ('header' === $side) {
            $this->print_shell_open();
        }
        $template = $this->capture(function () use ($side) {
            $this->print_template($side);
        });
        $closing = 'footer' === $side ? $this->capture(function () {
            $this->print_shell_close();
        }) : '';

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
        $discarded = $this->capture(static function () use ($templates, $args) {
            locate_template($templates, true, true, $args);
        });

        $wrappers = (new LayoutWrapperReader())->read($discarded, $side) ?? [
            'before' => '',
            'after' => '',
        ];

        // PHPCS - the template and shell are already escaped output; the wrappers are theme markup.
        echo $wrappers['before'], $template, $wrappers['after'], $closing; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /** Returns what $render printed, closing the buffer even if it throws. */
    private function capture(callable $render): string
    {
        ob_start();
        try {
            $render();

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    /** The document opening through wp_body_open(), printed before the Header template. */
    protected function print_shell_open(): void
    {
        require ELEMACY_PATH . 'src/Modules/ThemeBuilder/Views/theme-shell-open.php';
    }

    /** The Elemacy template for $side ('header' or 'footer'). */
    protected function print_template(string $side): void
    {
        'header' === $side ? $this->manager->render_header() : $this->manager->render_footer();
    }

    /** The footer hook and the document closing, printed after the Footer template. */
    protected function print_shell_close(): void
    {
        require ELEMACY_PATH . 'src/Modules/ThemeBuilder/Views/theme-shell-close.php';
    }
}
