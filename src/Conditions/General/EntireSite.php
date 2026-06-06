<?php

namespace Elemacy\Conditions\General;

defined('ABSPATH') || exit;

use Elemacy\Conditions\BaseCondition;
use Elemacy\Conditions\DTO\ConditionRuleDTO;

class EntireSite extends BaseCondition
{
    public function get_name(): string
    {
        return 'general/entire_site';
    }

    public function get_type(): string
    {
        return 'general';
    }

    public function get_label(): string
    {
        return __('Entire Site', 'elemacy');
    }

    public function check(ConditionRuleDTO $rule): bool
    {
        return true;
    }
}
