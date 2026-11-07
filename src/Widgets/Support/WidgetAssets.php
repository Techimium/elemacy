<?php

namespace Elemacy\Widgets\Support;

defined('ABSPATH') || exit;

use Elemacy\Support\Utils;

/**
 * Registers a widget's own script or stylesheet from its folder. Every widget
 * keeps its sources in `<widget folder>/assets/js` and `assets/css`, so the
 * URL follows from the folder and the file name, and the handle stays
 * `elemacy-<file>` wherever the widget lives.
 *
 * @internal
 */
final class WidgetAssets
{
    /**
     * @param string   $widget_dir Plugin-relative widget folder, e.g. `src/Widgets/NavMenu`.
     * @param string   $file       File name without extension; also names the handle.
     * @param string[] $extra_deps Handles this script needs besides the frontend runtime.
     */
    public static function register_script(string $widget_dir, string $file, array $extra_deps = []): void
    {
        wp_register_script(
            'elemacy-' . $file,
            Utils::get_plugin_url($widget_dir . '/assets/js/' . $file . '.js'),
            array_merge(['jquery', 'elemacy-frontend'], $extra_deps),
            ELEMACY_VERSION,
            true
        );
    }

    /**
     * @param string $widget_dir Plugin-relative widget folder, e.g. `src/Widgets/NavMenu`.
     * @param string $file       File name without extension; also names the handle.
     */
    public static function register_style(string $widget_dir, string $file): void
    {
        wp_register_style(
            'elemacy-' . $file,
            Utils::get_plugin_url($widget_dir . '/assets/css/' . $file . '.css'),
            ['elemacy-core'],
            ELEMACY_VERSION
        );
    }
}
