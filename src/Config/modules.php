<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\ThemeBuilder;
use Elemacy\Modules\DynamicTags\DynamicTags;
use Elemacy\Modules\LoopBuilder\LoopBuilder;
use Elemacy\Modules\FormBuilder\FormBuilder;
use Elemacy\Modules\CustomCss\CustomCss;
use Elemacy\Modules\Popups\Popups;

return [
    ThemeBuilder::class,
    DynamicTags::class,
    LoopBuilder::class,
    FormBuilder::class,
    CustomCss::class,
    Popups::class,
];
