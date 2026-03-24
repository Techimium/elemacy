<?php

use Elemacy\Modules\Widgets\Widgets\LoopCarousel;
use Elemacy\Modules\Widgets\Widgets\LoopGrid;
use Elemacy\Modules\Widgets\Widgets\NavMenu;
use Elemacy\Modules\Widgets\Widgets\Form;

return [
    [
        'name' => 'elemacy_nav_menu',
        'title' => 'Elemacy Nav Menu',
        'icon' => 'eicon-nav-menu',
        'class' => NavMenu::class,
    ],
    [
        'name' => 'elemacy-loop-grid',
        'title' => 'Loop Builder',
        'icon' => 'eicon-loop-builder',
        'class' => LoopGrid::class,
    ],
    [
        'name' => 'elemacy-loop-carousel',
        'title' => 'Loop Carousel',
        'icon' => 'eicon-carousel',
        'class' => LoopCarousel::class,
    ],
    [
        'name' => 'elemacy-form',
        'title' => 'Form Builder',
        'icon' => 'eicon-form-horizontal',
        'class' => Form::class,
    ],
];
