<?php

namespace Elemacy\Modules\Popups\Rules\Mock;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Rules\MockRule;

class Sessions extends MockRule
{
    public function get_name(): string
    {
        return 'sessions';
    }

    public function get_label(): string
    {
        return __('After X sessions', 'elemacy');
    }

    public function get_config_schema(): array
    {
        return [
            [
                'key'     => 'count',
                'label'   => __('Sessions', 'elemacy'),
                'type'    => 'number',
                'default' => 2,
                'min'     => 1,
            ],
        ];
    }
}
