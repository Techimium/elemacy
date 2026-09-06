<?php

namespace Elemacy\Modules\Popups\Triggers;

defined('ABSPATH') || exit;

/**
 * A non-functional stand-in for a trigger provided by an extension.
 *
 * Free registers mocks so the options are visible (and locked) in the UI.
 * When elemacy-pro is active it registers the real trigger under the same
 * name, overriding the mock in {@see \Elemacy\Modules\Popups\Services\TriggerManager}.
 */
abstract class MockTrigger extends BaseTrigger
{
    public function is_mock(): bool
    {
        return true;
    }
}
