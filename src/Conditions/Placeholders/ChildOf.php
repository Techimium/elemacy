<?php

namespace Elemacy\Conditions\Placeholders;

defined('ABSPATH') || exit;

use Elemacy\Conditions\Specificity;
use Elemacy\Conditions\DTO\ConditionRuleDTO;

/**
 * @internal
 */
class ChildOf extends PlaceholderCondition
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
