<?php

namespace Elemacy\Conditions\Mock;

defined('ABSPATH') || exit;

use Elemacy\Conditions\MockCondition;

class DateArchive extends MockCondition
{
    public function get_name(): string
    {
        return 'archive/date';
    }

    public function get_type(): string
    {
        return 'archive';
    }

    public function get_label(): string
    {
        return __('Date Archive', 'elemacy');
    }
}
