<?php

defined('ABSPATH') || exit;

use Elemacy\Widgets\AcfAccordion\AcfAccordion;
use Elemacy\Widgets\AcfGallery\AcfGallery;
use Elemacy\Widgets\AcfIconList\AcfIconList;
use Elemacy\Widgets\NavMenu\NavMenu;
use Elemacy\Widgets\Search\Search;
use Elemacy\Widgets\SiteLogo\SiteLogo;

/**
 * The widget platform's own general-purpose widgets. A feature module lists
 * its widgets in its own Config/widgets.php, returned by Module::get_widgets().
 */
return [
    [
        'name'  => 'elemacy-site-logo',
        'title' => __('Site Logo', 'elemacy'),
        'icon'  => 'image',
        'description' => __('Display your site logo anywhere on the page, linked to your homepage by default.', 'elemacy'),
        'class' => SiteLogo::class,
    ],
    [
        'name' => 'elemacy-nav-menu',
        'title' => __('Elemacy Nav Menu', 'elemacy'),
        'icon' => 'menu',
        'description' => __('A responsive navigation menu with dropdown submenus and a mobile toggle button.', 'elemacy'),
        'class' => NavMenu::class,
    ],
    [
        'name'  => 'elemacy-search',
        'title' => __('Search', 'elemacy'),
        'icon'  => 'search',
        'description' => __('A search box or icon that opens a full-screen search overlay for your visitors.', 'elemacy'),
        'class' => Search::class,
    ],
    [
        'name'     => 'elemacy-acf-accordion',
        'title'    => __('ACF Accordion', 'elemacy'),
        'icon'     => 'rows-3',
        'description' => __('Turn any ACF repeater field into collapsible accordion panels for FAQs and more.', 'elemacy'),
        'class'    => AcfAccordion::class,
        'requires' => 'acf',
    ],
    [
        'name'     => 'elemacy-acf-icon-list',
        'title'    => __('ACF Icon List', 'elemacy'),
        'icon'     => 'list',
        'description' => __('Show an ACF repeater field as a tidy list with a custom icon beside every item.', 'elemacy'),
        'class'    => AcfIconList::class,
        'requires' => 'acf',
    ],
    [
        'name'     => 'elemacy-acf-gallery',
        'title'    => __('ACF Gallery', 'elemacy'),
        'icon'     => 'image',
        'description' => __('Show any ACF gallery field as a clean, responsive image gallery on your pages.', 'elemacy'),
        'class'    => AcfGallery::class,
        'requires' => 'acf',
    ],
];
