<?php

namespace Elemacy\Modules\Popups\Rules\Placeholders;

defined('ABSPATH') || exit;


/**
 * @internal
 */
class Referrer extends PlaceholderRule
{
    public function get_name(): string
    {
        return 'referrer';
    }

    public function get_label(): string
    {
        return __('Arriving from URL', 'elemacy');
    }

    public function get_config_schema(): array
    {
        return [
            [
                'key'     => 'url',
                'label'   => __('Referrer URL', 'elemacy'),
                'type'    => 'text',
                'default' => '',
            ],
        ];
    }
}
