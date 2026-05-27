<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\ThemeBuilder;
use Elemacy\Modules\DynamicTags\DynamicTags;
use Elemacy\Modules\Widgets\Widgets;
use Elemacy\Modules\CustomCss\CustomCss;
use Elemacy\Placeholders\AnimationsPlaceholder;

return [
    ThemeBuilder::class,
    DynamicTags::class,
    Widgets::class,
    CustomCss::class,

    // Promotional modules
    AnimationsPlaceholder::class,
];
