<?php

namespace Elemacy\Conditions\Mock;

defined('ABSPATH') || exit;

use Elemacy\Conditions\Specificity;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Conditions\MockCondition;

class NotFound extends MockCondition
{
    public function get_name(): string
    {
        return 'general/not_found';
    }

    public function get_type(): string
    {
        return 'general';
    }

    public function get_label(): string
    {
        return __('404 Not Found', 'elemacy');
    }

    /** The 404 state. */
    public function get_specificity(ConditionRuleDTO $rule): int
    {
        return Specificity::TYPE;
    }
}
