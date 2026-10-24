<?php

namespace Elemacy\Conditions\Singular;

defined('ABSPATH') || exit;

use Elemacy\Conditions\Specificity;
use Elemacy\Conditions\BaseCondition;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Support\PostTypes;

class PostType extends BaseCondition
{
    public function get_name(): string
    {
        return 'singular/post_type';
    }

    public function get_type(): string
    {
        return 'singular';
    }

    public function get_label(): string
    {
        return __('Singular', 'elemacy');
    }

    /** One post type with a value; every singular document without one. */
    public function get_specificity(ConditionRuleDTO $rule): int
    {
        return Specificity::narrowed($rule->value, Specificity::TYPE, Specificity::SECTION);
    }

    public function check(ConditionRuleDTO $rule): bool
    {
        return $rule->value === '' ? is_singular() : is_singular($rule->value);
    }

    public function get_sub_values(): array
    {
        return PostTypes::as_sub_values();
    }
}
