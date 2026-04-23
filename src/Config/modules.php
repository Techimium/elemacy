<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\ThemeBuilder;
use Elemacy\Modules\DynamicTags\DynamicTags;
use Elemacy\Modules\Widgets\Widgets;
use Elemacy\Modules\Controls\Controls;

return [
    ThemeBuilder::class,
    DynamicTags::class,
    Widgets::class,
    Controls::class,
];
