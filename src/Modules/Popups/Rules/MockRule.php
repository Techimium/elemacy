<?php

namespace Elemacy\Modules\Popups\Rules;

defined('ABSPATH') || exit;

/**
 * A non-functional stand-in for an advanced rule provided by an extension.
 *
 * Free registers mocks so the options are visible (and locked) in the UI.
 * When elemacy-pro is active it registers the real rule under the same name,
 * overriding the mock in {@see \Elemacy\Modules\Popups\Services\RuleManager}.
 */
abstract class MockRule extends BaseRule
{
    public function is_mock(): bool
    {
        return true;
    }
}
