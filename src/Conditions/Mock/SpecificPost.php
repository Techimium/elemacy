<?php

namespace Elemacy\Conditions\Mock;

defined('ABSPATH') || exit;

use Elemacy\Conditions\Specificity;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Conditions\MockCondition;

class SpecificPost extends MockCondition
{
    public function get_name(): string
    {
        return 'singular/specific_post';
    }

    public function get_type(): string
    {
        return 'singular';
    }

    public function get_label(): string
    {
        return __('Specific Post / Page', 'elemacy');
    }

    /** As Pro's real condition: a rule names one post. */
    public function requires_value(): bool
    {
        return true;
    }

    /** One post with a value; every singular document without one. */
    public function get_specificity(ConditionRuleDTO $rule): int
    {
        return Specificity::narrowed($rule->value, Specificity::OBJECT, Specificity::SECTION);
    }
}
