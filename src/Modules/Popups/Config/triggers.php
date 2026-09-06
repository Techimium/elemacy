<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Triggers\Click;
use Elemacy\Modules\Popups\Triggers\Mock\ExitIntent;
use Elemacy\Modules\Popups\Triggers\Mock\Inactivity;
use Elemacy\Modules\Popups\Triggers\Mock\OnPopupClose;
use Elemacy\Modules\Popups\Triggers\Mock\Scroll;
use Elemacy\Modules\Popups\Triggers\Mock\ScrollToElement;
use Elemacy\Modules\Popups\Triggers\PageLoad;

/**
 * Triggers free ships, as class-strings. Mock triggers (Triggers\Mock\*) are
 * non-functional placeholders shown (and locked) in the UI; elemacy-pro
 * overrides each by name with a real implementation when active.
 *
 * @see \Elemacy\Modules\Popups\Services\TriggerRuleBootstrap::register_triggers()
 */
return [
    PageLoad::class,
    Click::class,

    // Mock triggers — overridden by elemacy-pro under the same name when active.
    Scroll::class,
    ExitIntent::class,
    Inactivity::class,
    ScrollToElement::class,
    OnPopupClose::class,
];
