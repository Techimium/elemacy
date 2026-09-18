<?php

namespace Elemacy\Modules\Popups\Triggers;

defined('ABSPATH') || exit;

/**
 * A non-functional stand-in for a trigger provided by an extension.
 *
 * Free registers mocks so the options are visible (and locked) in the UI.
 * When elemacy-pro is active it registers the real trigger under the same
 * name, overriding the mock in {@see \Elemacy\Modules\Popups\Services\TriggerManager}.
 *
 * {@see \Elemacy\Modules\Popups\Services\PopupDisplayResolver} treats
 * `is_mock()` as unavailable and suspends the whole popup before its config
 * ever reaches the browser, so a mock trigger is never shipped to the client
 * to silently fail to fire.
 */
abstract class MockTrigger extends BaseTrigger
{
    public function is_mock(): bool
    {
        return true;
    }
}
