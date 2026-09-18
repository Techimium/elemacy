<?php

namespace Elemacy\Conditions;

defined('ABSPATH') || exit;

use Elemacy\Conditions\DTO\ConditionRuleDTO;

/**
 * A non-functional stand-in for a condition provided by an extension.
 *
 * Free registers mocks so the options are visible (and disabled) in the UI.
 * When the extension (e.g. elemacy-pro) is active it registers the real
 * condition under the same name, overriding the mock in
 * {@see \Elemacy\Conditions\ConditionManager}.
 *
 * {@see \Elemacy\Conditions\ConditionEvaluator::evaluate()} treats `is_mock()`
 * as unavailable and suspends the dependent item before ever calling
 * `check()`, so the `false` below is only a safe default for any other
 * direct caller — it is not how unavailability is communicated at runtime.
 */
abstract class MockCondition extends BaseCondition
{
    public function check(ConditionRuleDTO $rule): bool
    {
        return false;
    }

    public function is_mock(): bool
    {
        return true;
    }
}
