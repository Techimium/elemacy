<?php
/**
 * A full header-side shell: the document opening, then the Header template.
 * Used where Elemacy owns the whole shell (a theme with no header.php).
 */

use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

require __DIR__ . '/theme-shell-open.php';

ThemeBuilderManager::instance()->render_header();
