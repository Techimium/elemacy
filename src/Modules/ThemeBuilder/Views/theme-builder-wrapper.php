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

get_header();

did_action('wp_body_open') || wp_body_open();

$elemacy_manager->render_template($elemacy_content_id);


get_footer();
?>
