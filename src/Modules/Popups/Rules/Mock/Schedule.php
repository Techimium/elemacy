<?php

namespace Elemacy\Modules\Popups\Rules\Mock;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Rules\MockRule;

class Schedule extends MockRule
{
    public function get_name(): string
    {
        return 'schedule';
    }

    public function get_label(): string
    {
        return __('Between dates', 'elemacy');
    }

    public function get_config_schema(): array
    {
        return [
            [
                'key'     => 'start',
                'label'   => __('Start date', 'elemacy'),
                'type'    => 'datetime',
                'default' => '',
            ],
            [
                'key'     => 'end',
                'label'   => __('End date', 'elemacy'),
                'type'    => 'datetime',
                'default' => '',
            ],
        ];
    }
}
