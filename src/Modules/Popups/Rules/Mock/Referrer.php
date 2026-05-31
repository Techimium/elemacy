<?php

namespace Elemacy\Modules\Popups\Rules\Mock;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Rules\MockRule;

class Referrer extends MockRule
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
