<?php

use Elemacy\Modules\Widgets\Widgets\LoopCarousel;
use Elemacy\Modules\Widgets\Widgets\LoopGrid;
use Elemacy\Modules\Widgets\Widgets\NavMenu;
use Elemacy\Modules\Widgets\Widgets\Form;

return [
    [
        'name' => 'elemacy_nav_menu',
        'title' => 'Elemacy Nav Menu',
        'icon' => 'menu',
        'class' => NavMenu::class,
    ],
    [
        'name' => 'elemacy-loop-grid',
        'title' => 'Loop Builder',
        'icon' => 'layout-panel-top',
        'class' => LoopGrid::class,
    ],
    [
        'name' => 'elemacy-loop-carousel',
        'title' => 'Loop Carousel',
        'icon' => 'gallery-horizontal-end',
        'class' => LoopCarousel::class,
    ],
    [
        'name' => 'elemacy-form',
        'title' => 'Form Builder',
        'icon' => 'rows-3',
        'class' => Form::class,
    ],
];
