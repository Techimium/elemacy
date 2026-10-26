<?php
/**
 * The document shell up to and including wp_body_open(): everything a
 * header-side replacement prints before the Header template.
 */

use Elementor\Utils;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <?php
    // PHPCS - not a user input.
    echo Utils::get_meta_viewport('theme-builder'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    ?>
    <?php if (!current_theme_supports('title-tag')) : ?>
        <title>
            <?php
            // PHPCS - already escaped by WordPress.
            echo wp_get_document_title(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
        </title>
    <?php endif; ?>
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
    <?php
    did_action('wp_body_open') || wp_body_open();
    ?>
