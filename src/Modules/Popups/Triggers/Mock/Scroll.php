<?php

namespace Elemacy\Modules\Popups\Triggers\Mock;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Triggers\MockTrigger;

/**
 * Mock placeholder — the real "On Scroll" trigger ships in elemacy-pro, which
 * overrides this by name when active. Shown locked in the UI to tease pro.
 */
class Scroll extends MockTrigger
{
    public function get_name(): string
    {
        return 'scroll';
    }

    public function get_label(): string
    {
        return __('On Scroll', 'elemacy');
    }

    public function get_config_schema(): array
    {
        return [
            [
                'key'     => 'percentage',
                'label'   => __('Scroll %', 'elemacy'),
                'type'    => 'number',
                'default' => 50,
                'min'     => 0,
                'max'     => 100,
                'unit'    => '%',
            ],
        ];
    }
}
