<?php

namespace Elemacy\Conditions\Mock;

defined('ABSPATH') || exit;

use Elemacy\Conditions\Specificity;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Conditions\MockCondition;

class ChildOf extends MockCondition
{
    public function get_name(): string
    {
        return 'singular/child_of';
    }

    public function get_type(): string
    {
        return 'singular';
    }

    public function get_label(): string
    {
        return __('Child Of', 'elemacy');
    }

    /** Always names one ancestor; never matches without a value. */
    public function get_specificity(ConditionRuleDTO $rule): int
    {
        return Specificity::OBJECT;
    }
}
