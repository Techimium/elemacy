<?php

namespace Elemacy\Modules\Popups\Rules\Mock;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Rules\MockRule;

/**
 * Mock placeholder — the real "Show once per" rule ships in elemacy-pro, which
 * overrides this by name when active. Shown locked in the UI to tease pro.
 */
class OncePer extends MockRule
{
    public function get_name(): string
    {
        return 'once_per';
    }

    public function get_label(): string
    {
        return __('Show once per', 'elemacy');
    }

    public function get_config_schema(): array
    {
        return [
            [
                'key'     => 'period',
                'label'   => __('Period', 'elemacy'),
                'type'    => 'select',
                'default' => 'session',
                'options' => [
                    [
                        'value' => 'session',
                        'label' => __('Session', 'elemacy'),
                    ],
                    [
                        'value' => 'day',
                        'label' => __('Day', 'elemacy'),
                    ],
                    [
                        'value' => 'week',
                        'label' => __('Week', 'elemacy'),
                    ],
                    [
                        'value' => 'forever',
                        'label' => __('Forever', 'elemacy'),
                    ],
                ],
            ],
        ];
    }
}
