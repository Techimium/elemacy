<?php

namespace Elemacy\Conditions\Singular;

defined('ABSPATH') || exit;

use Elemacy\Conditions\BaseCondition;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Conditions\Support\PostTypes;

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

    public function check(ConditionRuleDTO $rule): bool
    {
        return $rule->value === '' ? is_singular() : is_singular($rule->value);
    }

    public function get_sub_values(): array
    {
        return PostTypes::as_sub_values();
    }
}
