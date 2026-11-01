<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Triggers\Click;
use Elemacy\Modules\Popups\Triggers\Placeholders\ExitIntent;
use Elemacy\Modules\Popups\Triggers\Placeholders\Inactivity;
use Elemacy\Modules\Popups\Triggers\Placeholders\OnPopupClose;
use Elemacy\Modules\Popups\Triggers\Placeholders\Scroll;
use Elemacy\Modules\Popups\Triggers\Placeholders\ScrollToElement;
use Elemacy\Modules\Popups\Triggers\PageLoad;

/**
 * Triggers free ships, as class-strings. Placeholder triggers (Triggers\Placeholders\*) are
 * non-functional placeholders shown (and locked) in the UI; elemacy-pro
 * overrides each by name with a real implementation when active.
 *
 * @see \Elemacy\Modules\Popups\Services\TriggerRuleBootstrap::register_triggers()
 */
return [
    PageLoad::class,
    Click::class,

    // Placeholder triggers — overridden by elemacy-pro under the same name when active.
    Scroll::class,
    ExitIntent::class,
    Inactivity::class,
    ScrollToElement::class,
    OnPopupClose::class,
];
