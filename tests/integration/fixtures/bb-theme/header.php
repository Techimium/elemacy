<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<link rel="icon" href="data:,">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="fl-page">
<?php do_action('fl_before_header'); ?>
<?php if (apply_filters('fl_header_enabled', true)) : ?>
<header class="fl-page-header">BB native header</header>
<?php endif; ?>
<div id="fl-main-content" class="fl-page-content">
