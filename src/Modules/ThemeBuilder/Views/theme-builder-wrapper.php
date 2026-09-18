<?php
/**
 * Theme Builder Wrapper Template
 * 
 * This template is used when a Theme Builder template is active.
 */

use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$elemacy_manager = ThemeBuilderManager::instance();
$elemacy_content_id = $elemacy_manager->get_location_template_id();

// A block theme has no classic header.php of its own, but plain
// `'' !== locate_template(['header.php'])` is the wrong existence check:
// given only that one candidate, locate_template() always finds
// *something*, silently falling through to WordPress's own deprecated
// wp-includes/theme-compat/header.php stub when the active theme has none.
// Excluding any match inside that specific fallback directory keeps
// locate_template() as the single source of truth for theme hierarchy
// (child-theme override order included) while still correctly treating
// "only the deprecated stub matched" as "this theme has no header.php".
// Elemacy owns the whole shell itself in that case; a classic theme's own
// header.php (compatibility-adapter-suppressed where a header template is
// configured) still owns it otherwise, exactly as before.
$elemacy_theme_compat_dir = ABSPATH . WPINC . '/theme-compat/';
$elemacy_located_header = locate_template(['header.php']);
$elemacy_located_footer = locate_template(['footer.php']);

if ('' !== $elemacy_located_header && 0 !== strpos($elemacy_located_header, $elemacy_theme_compat_dir)) {
    get_header();
} else {
    require ELEMACY_PATH . 'src/Modules/ThemeBuilder/Views/theme-support-header.php';
}

did_action('wp_body_open') || wp_body_open();

$elemacy_manager->render_template($elemacy_content_id, 'location');

if ('' !== $elemacy_located_footer && 0 !== strpos($elemacy_located_footer, $elemacy_theme_compat_dir)) {
    get_footer();
} else {
    require ELEMACY_PATH . 'src/Modules/ThemeBuilder/Views/theme-support-footer.php';
}
?>
