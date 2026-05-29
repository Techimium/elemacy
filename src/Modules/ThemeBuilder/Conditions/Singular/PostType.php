<?php

namespace Elemacy\Modules\ThemeBuilder\Conditions\Singular;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Conditions\BaseCondition;
use Elemacy\Modules\ThemeBuilder\DTO\ConditionRuleDTO;
use Elemacy\Modules\ThemeBuilder\Support\PostTypes;

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
