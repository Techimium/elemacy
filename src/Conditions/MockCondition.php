<?php

namespace Elemacy\Conditions;

defined('ABSPATH') || exit;

use Elemacy\Conditions\DTO\ConditionRuleDTO;

/** A non-functional stand-in for a condition provided by an extension. */
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
