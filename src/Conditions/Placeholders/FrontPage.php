<?php

namespace Elemacy\Conditions\Placeholders;

defined('ABSPATH') || exit;

use Elemacy\Conditions\Specificity;
use Elemacy\Conditions\DTO\ConditionRuleDTO;

/**
 * @internal
 */
class FrontPage extends PlaceholderCondition
{
    public function get_name(): string
    {
        return 'general/front_page';
    }

    public function get_type(): string
    {
        return 'general';
    }

    public function get_label(): string
    {
        return __('Front Page', 'elemacy');
    }

    /** Exactly one page: the site front page. */
    public function get_specificity(ConditionRuleDTO $rule): int
    {
        return Specificity::OBJECT;
    }
}
