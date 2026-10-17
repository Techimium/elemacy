<?php
/**
 * A full footer-side shell: the Footer template, then the document closing.
 * Used where Elemacy owns the whole shell (a theme with no footer.php).
 */

use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

ThemeBuilderManager::instance()->render_footer();

require __DIR__ . '/theme-shell-close.php';
