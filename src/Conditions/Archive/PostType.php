<?php

namespace Elemacy\Conditions\Archive;

defined('ABSPATH') || exit;

use Elemacy\Conditions\BaseCondition;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Conditions\Support\PostTypes;

class PostType extends BaseCondition
{
    public function get_name(): string
    {
        return 'archive/post_type';
    }

    public function get_type(): string
    {
        return 'archive';
    }

    public function get_label(): string
    {
        return __('Post Type Archive', 'elemacy');
    }

    public function check(ConditionRuleDTO $rule): bool
    {
        $value = $rule->value;

        if ($value === '') {
            return is_archive() || is_home();
        }

        if ($value === 'post') {
            return is_home() || (is_archive() && get_post_type() === 'post');
        }

        return is_post_type_archive($value);
    }

    public function get_sub_values(): array
    {
        return PostTypes::as_sub_values();
    }
}
