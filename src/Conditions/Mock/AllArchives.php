<?php

namespace Elemacy\Conditions\Mock;

defined('ABSPATH') || exit;

use Elemacy\Conditions\MockCondition;

class AllArchives extends MockCondition
{
    public function get_name(): string
    {
        return 'archive/all';
    }

    public function get_type(): string
    {
        return 'archive';
    }

    public function get_label(): string
    {
        return __('All Archives', 'elemacy');
    }
}
