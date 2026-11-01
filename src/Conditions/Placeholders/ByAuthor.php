<?php

namespace Elemacy\Conditions\Placeholders;

defined('ABSPATH') || exit;

use Elemacy\Conditions\Specificity;
use Elemacy\Conditions\DTO\ConditionRuleDTO;

/**
 * @internal
 */
class ByAuthor extends PlaceholderCondition
{
    public function get_name(): string
    {
        return 'singular/by_author';
    }

    public function get_type(): string
    {
        return 'singular';
    }

    public function get_label(): string
    {
        return __('By Author', 'elemacy');
    }

    /** One author with a value; every singular document without one. */
    public function get_specificity(ConditionRuleDTO $rule): int
    {
        return Specificity::narrowed($rule->value, Specificity::OBJECT, Specificity::SECTION);
    }
}
