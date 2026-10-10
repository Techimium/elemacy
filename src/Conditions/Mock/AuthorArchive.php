<?php

namespace Elemacy\Conditions\Mock;

defined('ABSPATH') || exit;

use Elemacy\Conditions\Specificity;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Conditions\MockCondition;

class AuthorArchive extends MockCondition
{
    public function get_name(): string
    {
        return 'archive/author';
    }

    public function get_type(): string
    {
        return 'archive';
    }

    public function get_label(): string
    {
        return __('Author Archive', 'elemacy');
    }

    /** One author archive with a value; every author archive without one. */
    public function get_specificity(ConditionRuleDTO $rule): int
    {
        return Specificity::narrowed($rule->value, Specificity::OBJECT, Specificity::TYPE);
    }
}
