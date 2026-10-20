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

    /**
     * The only condition for every search results page, so it is offered
     * wherever such a page is targeted: Search Results templates, and the
     * Header and Footer shown on them (and popups, as every condition is).
     */
    public function get_applicable_template_types(): array
    {
        return ['header', 'footer', 'search'];
    }

    public function check(ConditionRuleDTO $rule): bool
    {
        return is_search();
    }
}
