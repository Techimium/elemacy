<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\LoopBuilder\Widgets\LoopCarousel\LoopCarousel;
use Elemacy\Modules\LoopBuilder\Widgets\LoopGrid\LoopGrid;

return [
    [
        'name' => 'elemacy-loop-grid',
        'title' => __('Loop Builder', 'elemacy'),
        'icon' => 'layout-panel-top',
        'description' => __('Show posts or ACF repeater items in a grid, styled by your own loop template.', 'elemacy'),
        'class' => LoopGrid::class,
    ],
    [
        'name' => 'elemacy-loop-carousel',
        'title' => __('Loop Carousel', 'elemacy'),
        'icon' => 'gallery-horizontal-end',
        'description' => __('Slide through posts or ACF repeater items in a carousel styled by a loop template.', 'elemacy'),
        'class' => LoopCarousel::class,
    ],
];
