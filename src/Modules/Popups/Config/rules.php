<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Rules\FrequencyCap;
use Elemacy\Modules\Popups\Rules\LoggedInUsers;
use Elemacy\Modules\Popups\Rules\Placeholders\Browser;
use Elemacy\Modules\Popups\Rules\Placeholders\Devices;
use Elemacy\Modules\Popups\Rules\Placeholders\OncePer;
use Elemacy\Modules\Popups\Rules\Placeholders\PageViews;
use Elemacy\Modules\Popups\Rules\Placeholders\Referrer;
use Elemacy\Modules\Popups\Rules\Placeholders\Roles;
use Elemacy\Modules\Popups\Rules\Placeholders\Schedule;
use Elemacy\Modules\Popups\Rules\Placeholders\Sessions;

/**
 * Advanced rules free ships, as class-strings. Placeholder rules (Rules\Placeholders\*) are
 * non-functional placeholders shown (and locked) in the UI; elemacy-pro
 * overrides each by name with a real implementation when active.
 *
 * Free keeps "Show up to X times" (frequency cap) and "Hide for logged-in
 * users"; everything else is a pro feature represented here by a placeholder.
 *
 * @see \Elemacy\Modules\Popups\Services\TriggerRuleBootstrap::register_rules()
 */
return [
    FrequencyCap::class,
    LoggedInUsers::class,

    // Placeholder rules — overridden by elemacy-pro under the same name when active.
    OncePer::class,
    Devices::class,
    Schedule::class,
    PageViews::class,
    Sessions::class,
    Referrer::class,
    Roles::class,
    Browser::class,
];
