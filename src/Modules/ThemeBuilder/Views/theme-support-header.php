<?php
/**
 * A full header-side shell: the document opening, then the Header template.
 * Used where Elemacy owns the whole shell (a theme with no header.php).
 *
 * On a block theme theme-builder-wrapper.php sets $elemacy_block_theme_shell:
 * the page is then laid out in the theme's `.wp-site-blocks` wrapper, and a
 * side with no Elemacy template shows the theme's own template part.
 */

use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!empty($elemacy_block_theme_shell)) {
    ThemeBuilderManager::instance()->prepare_block_theme_sides();
}

require __DIR__ . '/theme-shell-open.php';

if (!empty($elemacy_block_theme_shell)) {
    echo '<div class="wp-site-blocks">';
    ThemeBuilderManager::instance()->render_block_theme_side('header');
} else {
    ThemeBuilderManager::instance()->render_header();
}
