<?php
/**
 * A full footer-side shell: the Footer template, then the document closing.
 * Used where Elemacy owns the whole shell (a theme with no footer.php).
 * See theme-support-header.php for $elemacy_block_theme_shell.
 */

use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!empty($elemacy_block_theme_shell)) {
    ThemeBuilderManager::instance()->render_block_theme_side('footer');
    echo '</div>';
} else {
    ThemeBuilderManager::instance()->render_footer();
}

require __DIR__ . '/theme-shell-close.php';
