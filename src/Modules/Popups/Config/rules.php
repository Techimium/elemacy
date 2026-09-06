<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Rules\FrequencyCap;
use Elemacy\Modules\Popups\Rules\LoggedInUsers;
use Elemacy\Modules\Popups\Rules\Mock\Browser;
use Elemacy\Modules\Popups\Rules\Mock\Devices;
use Elemacy\Modules\Popups\Rules\Mock\OncePer;
use Elemacy\Modules\Popups\Rules\Mock\PageViews;
use Elemacy\Modules\Popups\Rules\Mock\Referrer;
use Elemacy\Modules\Popups\Rules\Mock\Roles;
use Elemacy\Modules\Popups\Rules\Mock\Schedule;
use Elemacy\Modules\Popups\Rules\Mock\Sessions;

/**
 * Advanced rules free ships, as class-strings. Mock rules (Rules\Mock\*) are
 * non-functional placeholders shown (and locked) in the UI; elemacy-pro
 * overrides each by name with a real implementation when active.
 *
 * Free keeps "Show up to X times" (frequency cap) and "Hide for logged-in
 * users"; everything else is a pro feature represented here by a mock.
 *
 * @see \Elemacy\Modules\Popups\Services\TriggerRuleBootstrap::register_rules()
 */
return [
    FrequencyCap::class,
    LoggedInUsers::class,

    // Mock rules — overridden by elemacy-pro under the same name when active.
    OncePer::class,
    Devices::class,
    Schedule::class,
    PageViews::class,
    Sessions::class,
    Referrer::class,
    Roles::class,
    Browser::class,
];
