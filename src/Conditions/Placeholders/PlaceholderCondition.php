<?php

namespace Elemacy\Conditions\Placeholders;

defined('ABSPATH') || exit;

use Elemacy\Conditions\BaseCondition;
use Elemacy\Conditions\DTO\ConditionRuleDTO;

/**
 * A Pro placeholder: lists a condition that only Pro implements, so users can
 * see what Pro adds. It declares a name, a label and a settings shape and
 * never contains the feature's logic.
 *
 * @internal
 */
abstract class PlaceholderCondition extends BaseCondition
{
    public function check(ConditionRuleDTO $rule): bool
    {
        return false;
    }

    public function is_placeholder(): bool
    {
        return true;
    }
}
