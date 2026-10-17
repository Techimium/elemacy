<?php

namespace Elemacy\Conditions\Search;

defined('ABSPATH') || exit;

use Elemacy\Conditions\Specificity;
use Elemacy\Conditions\BaseCondition;
use Elemacy\Conditions\DTO\ConditionRuleDTO;

/**
 * Any search results page, whatever post types are searched. The narrower
 * "Search Results: Post Type" is a Pro condition.
 */
class AllResults extends BaseCondition
{
    public function get_name(): string
    {
        return 'search/all';
    }

    public function get_type(): string
    {
        return 'search';
    }

    public function get_label(): string
    {
        return __('Search Results', 'elemacy');
    }

    /** The search results state. */
    public function get_specificity(ConditionRuleDTO $rule): int
    {
        return Specificity::TYPE;
    }

    public function check(ConditionRuleDTO $rule): bool
    {
        return is_search();
    }
}
