<?php

namespace Elemacy\Conditions\Mock;

defined('ABSPATH') || exit;

use Elemacy\Conditions\Specificity;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Conditions\MockCondition;
use Elemacy\Support\PostTypes;

class SearchPostType extends MockCondition
{
    public function get_name(): string
    {
        return 'search/post_type';
    }

    public function get_type(): string
    {
        return 'search';
    }

    public function get_label(): string
    {
        return __('Search Results: Post Type', 'elemacy');
    }

    /** One post type with a value; every search without one. */
    public function get_specificity(ConditionRuleDTO $rule): int
    {
        return Specificity::narrowed($rule->value, Specificity::OBJECT, Specificity::TYPE);
    }

    public function get_sub_values(): array
    {
        return PostTypes::as_sub_values();
    }
}
