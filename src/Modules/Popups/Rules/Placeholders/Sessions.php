<?php

namespace Elemacy\Modules\Popups\Rules\Placeholders;

defined('ABSPATH') || exit;


/**
 * @internal
 */
class Sessions extends PlaceholderRule
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
