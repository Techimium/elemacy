<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\Widgets\Widgets\LoopCarousel;
use Elemacy\Modules\Widgets\Widgets\LoopGrid;
use Elemacy\Modules\Widgets\Widgets\NavMenu;
use Elemacy\Modules\Widgets\Widgets\Form;
use Elemacy\Modules\Widgets\Widgets\SiteLogo;

return [
    [
        'name'  => 'elemacy-site-logo',
        'title' => __('Site Logo', 'elemacy'),
        'icon'  => 'image',
        'class' => SiteLogo::class,
    ],
    [
        'name' => 'elemacy_nav_menu',
        'title' => __('Elemacy Nav Menu', 'elemacy'),
        'icon' => 'menu',
        'class' => NavMenu::class,
    ],
    [
        'name' => 'elemacy-loop-grid',
        'title' => __('Loop Builder', 'elemacy'),
        'icon' => 'layout-panel-top',
        'class' => LoopGrid::class,
    ],
    [
        'name' => 'elemacy-loop-carousel',
        'title' => __('Loop Carousel', 'elemacy'),
        'icon' => 'gallery-horizontal-end',
        'class' => LoopCarousel::class,
    ],
    [
        'name' => 'elemacy-form',
        'title' => __('Form Builder', 'elemacy'),
        'icon' => 'rows-3',
        'class' => Form::class,
    ],
];
