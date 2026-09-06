<?php

namespace Elemacy\Support;

defined('ABSPATH') || exit;

class Brand
{
    /**
     * The selected Elemacy bracket mark, normalized to an 80×80 viewBox. Kept as a single
     * source so every place that shows the logo (admin menu, Elementor section
     * headings, …) stays in sync. Fills are omitted here so callers can colorize.
     */
    protected static function mark_paths(): string
    {
        return '<path d="M0 0H80V33.5L69.5 39.5V10.5H10.5V33.5L0 39.5ZM0 46.5L10.5 40.5V69.5H69.5V46.5L80 40.5V80H0Z"/>'
            . '<path d="M21 19.5H59V27H29V36H53V43.5H29V53H59V60.5H21Z"/>';
    }

    /** Standalone mark for notices and other HTML surfaces. */
    public static function svg_mark(int $size = 20, string $color = 'currentColor'): string
    {
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" width="%1$d" height="%1$d"'
                . ' fill="%2$s" role="img" aria-label="Elemacy">%3$s</svg>',
            $size,
            esc_attr($color),
            self::mark_paths()
        );
    }

    /** White badge; WordPress recolors the fill for its admin menu states. */
    public static function data_uri(): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(self::svg_mark(20, 'white'));
    }

    /**
     * Inline brand mark for markup rendered as HTML — e.g. Elementor section
     * labels, which print the label through an unescaped Mustache
     * (`{{{ data.label }}}`), so SVG is safe there. `currentColor` keeps it aligned
     * with the surrounding text color in both the light and dark editor themes.
     */
    public static function inline_mark(int $size = 13): string
    {
        return sprintf(
            '<svg class="elemacy-brand-mark" viewBox="0 0 80 80" width="%1$d" height="%1$d"'
                . ' fill="currentColor" role="img" aria-label="Elemacy" xmlns="http://www.w3.org/2000/svg"'
                . ' style="vertical-align:middle;margin-inline-start:4px;opacity:.75;">%2$s</svg>',
            $size,
            self::mark_paths()
        );
    }
}
