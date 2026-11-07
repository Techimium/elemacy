<?php

namespace Elemacy\Conditions\Placeholders;

defined('ABSPATH') || exit;

use Elemacy\Conditions\Specificity;
use Elemacy\Conditions\DTO\ConditionRuleDTO;

/**
 * @internal
 */
class Blog extends PlaceholderCondition
{
    public function get_name(): string
    {
        return 'general/blog';
    }

    public function get_type(): string
    {
        return 'general';
    }

    public function get_label(): string
    {
        return __('Blog / Posts Page', 'elemacy');
    }

    /** The posts index. */
    public function get_specificity(ConditionRuleDTO $rule): int
    {
        return Specificity::TYPE;
    }
}
