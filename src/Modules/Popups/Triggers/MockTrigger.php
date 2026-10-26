<?php

namespace Elemacy\Modules\Popups\Triggers;

defined('ABSPATH') || exit;

/** A non-functional stand-in for a trigger provided by an extension. */
abstract class MockTrigger extends BaseTrigger
{
    public function is_mock(): bool
    {
        return true;
    }
}
