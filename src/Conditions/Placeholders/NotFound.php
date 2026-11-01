<?php

namespace Elemacy\Conditions\Placeholders;

defined('ABSPATH') || exit;

use Elemacy\Conditions\Specificity;
use Elemacy\Conditions\DTO\ConditionRuleDTO;

/**
 * @internal
 */
class NotFound extends PlaceholderCondition
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
