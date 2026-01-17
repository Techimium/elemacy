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

$manager = ThemeBuilderManager::instance();
$content_id = $manager->get_location_template_id();

get_header();


$manager->render_template($content_id);


get_footer();
?>