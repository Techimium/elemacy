<?php

namespace Elemacy\Support;

defined('ABSPATH') || exit;

class Brand
{
    /**
     * The Elemacy bracket mark, normalized to a 134×134 viewBox. Kept as a single
     * source so every place that shows the logo (admin menu, Elementor section
     * headings, …) stays in sync. Fills are omitted here so callers can colorize.
     */
    protected static function mark_paths(): string
    {
        return '<path d="M134 129L133.993 129.257C133.859 131.899 131.675 134 129 134H5C2.23858 134 0 131.761 0 129V86.8906H15V119H119V59.6719H134V129ZM129.257 0.00683594C131.899 0.14053 134 2.32472 134 5V46.6719H119V15H15V73.8906H0V5C1.99737e-06 2.32472 2.10111 0.140529 4.74316 0.00683594L5 0H129L129.257 0.00683594Z"/>'
            . '<rect x="34" y="87.1912" width="67" height="13.7941"/>'
            . '<rect x="34.9851" y="33" width="66.0147" height="12.8088"/>'
            . '<rect x="34.9851" y="59.603" width="47.2941" height="13.5126"/>'
            . '<rect x="34" y="33" width="16.9963" height="67.9853"/>';
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
            '<svg class="elemacy-brand-mark" viewBox="0 0 134 134" width="%1$d" height="%1$d"'
                . ' fill="currentColor" role="img" aria-label="Elemacy" xmlns="http://www.w3.org/2000/svg"'
                . ' style="vertical-align:middle;margin-inline-start:4px;opacity:.75;">%2$s</svg>',
            $size,
            self::mark_paths()
        );
    }
}
