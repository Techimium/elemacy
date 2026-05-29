<?php

namespace Elemacy\Modules\ThemeBuilder\Conditions;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\DTO\ConditionRuleDTO;

/**
 * A non-functional stand-in for a condition provided by an extension.
 *
 * Free registers mocks so the options are visible (and disabled) in the UI.
 * When the extension (e.g. elemacy-pro) is active it registers the real
 * condition under the same name, overriding the mock in
 * {@see \Elemacy\Modules\ThemeBuilder\Services\ConditionManager}.
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
