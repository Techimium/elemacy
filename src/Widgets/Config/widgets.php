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
        'class' => SiteLogo::class,
    ],
    [
        'name' => 'elemacy-nav-menu',
        'title' => __('Elemacy Nav Menu', 'elemacy'),
        'icon' => 'menu',
        'class' => NavMenu::class,
    ],
    [
        'name'  => 'elemacy-search',
        'title' => __('Search', 'elemacy'),
        'icon'  => 'search',
        'class' => Search::class,
    ],
    [
        'name'     => 'elemacy-acf-accordion',
        'title'    => __('ACF Accordion', 'elemacy'),
        'icon'     => 'rows-3',
        'class'    => AcfAccordion::class,
        'requires' => 'acf',
    ],
    [
        'name'     => 'elemacy-acf-icon-list',
        'title'    => __('ACF Icon List', 'elemacy'),
        'icon'     => 'list',
        'class'    => AcfIconList::class,
        'requires' => 'acf',
    ],
    [
        'name'     => 'elemacy-acf-gallery',
        'title'    => __('ACF Gallery', 'elemacy'),
        'icon'     => 'image',
        'class'    => AcfGallery::class,
        'requires' => 'acf',
    ],
];
