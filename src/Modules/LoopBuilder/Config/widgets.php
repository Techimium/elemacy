<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\LoopBuilder\Widgets\LoopCarousel\LoopCarousel;
use Elemacy\Modules\LoopBuilder\Widgets\LoopGrid\LoopGrid;
use Elemacy\Modules\LoopBuilder\Widgets\PostCarousel\PostCarousel;
use Elemacy\Modules\LoopBuilder\Widgets\PostGrid\PostGrid;

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
    [
        'name' => 'elemacy-post-grid',
        'title' => __('Post Grid', 'elemacy'),
        'icon' => 'layout-grid',
        'description' => __('List posts of any type as a grid, list or masonry of cards, with no template to build.', 'elemacy'),
        'class' => PostGrid::class,
    ],
    [
        'name' => 'elemacy-post-carousel',
        'title' => __('Post Carousel', 'elemacy'),
        'icon' => 'gallery-horizontal',
        'description' => __('Slide through posts of any type as cards, with no template to build.', 'elemacy'),
        'class' => PostCarousel::class,
    ],
];
