<?php

namespace Elemacy\Conditions\Mock;

defined('ABSPATH') || exit;

use Elemacy\Conditions\MockCondition;

class SpecificPost extends MockCondition
{
    public function get_name(): string
    {
        return 'singular/specific_post';
    }

    public function get_type(): string
    {
        return 'singular';
    }

    public function get_label(): string
    {
        return __('Specific Post / Page', 'elemacy');
    }
}
