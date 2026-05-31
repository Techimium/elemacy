<?php

namespace Elemacy\Conditions\Mock;

defined('ABSPATH') || exit;

use Elemacy\Conditions\MockCondition;

class FrontPage extends MockCondition
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
}
