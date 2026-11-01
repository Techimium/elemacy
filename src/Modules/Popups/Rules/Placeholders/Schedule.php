<?php

namespace Elemacy\Modules\Popups\Rules\Placeholders;

defined('ABSPATH') || exit;


/**
 * @internal
 */
class Schedule extends PlaceholderRule
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
