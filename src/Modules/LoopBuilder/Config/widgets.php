<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\LoopBuilder\Widgets\LoopCarousel\LoopCarousel;
use Elemacy\Modules\LoopBuilder\Widgets\LoopGrid\LoopGrid;

return [
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
];
