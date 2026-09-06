<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\ThemeBuilder;
use Elemacy\Modules\DynamicTags\DynamicTags;
use Elemacy\Modules\Widgets\Widgets;
use Elemacy\Modules\CustomCss\CustomCss;
use Elemacy\Modules\Popups\Popups;
use Elemacy\Modules\Mock\AnimationsPlaceholder;

return [
    ThemeBuilder::class,
    DynamicTags::class,
    Widgets::class,
    CustomCss::class,
    Popups::class,

    // Promotional modules
    AnimationsPlaceholder::class,
];
