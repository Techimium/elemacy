<?php

namespace Elemacy\Modules\Popups\Triggers\Mock;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Triggers\MockTrigger;

class Inactivity extends MockTrigger
{
    public function get_name(): string
    {
        return 'inactivity';
    }

    public function get_label(): string
    {
        return __('After Inactivity', 'elemacy');
    }

    public function get_config_schema(): array
    {
        return [
            [
                'key'     => 'delay',
                'label'   => __('Inactivity (seconds)', 'elemacy'),
                'type'    => 'number',
                'default' => 30,
                'min'     => 1,
            ],
        ];
    }
}
