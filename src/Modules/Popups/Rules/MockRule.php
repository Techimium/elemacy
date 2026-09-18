<?php

namespace Elemacy\Modules\Popups\Rules;

defined('ABSPATH') || exit;

/** A non-functional stand-in for an advanced rule provided by an extension. */
abstract class MockRule extends BaseRule
{
    public function is_mock(): bool
    {
        return true;
    }
}
