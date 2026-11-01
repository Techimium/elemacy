<?php

namespace Elemacy\Modules\Popups\Rules\Placeholders;

defined('ABSPATH') || exit;


/**
 * Pro placeholder: the real "Show on devices" rule ships in elemacy-pro,
 * which overrides this by name when active. Shown locked in the UI to tease pro.
 *
 * @internal
 */
class Devices extends PlaceholderRule
{
    public function get_name(): string
    {
        return 'devices';
    }

    public function get_label(): string
    {
        return __('Show on devices', 'elemacy');
    }

    public function get_config_schema(): array
    {
        return [
            [
                'key'     => 'allow',
                'label'   => __('Show on', 'elemacy'),
                'type'    => 'checkbox-group',
                'default' => ['desktop', 'tablet', 'mobile'],
                'options' => [
                    [
                        'value' => 'desktop',
                        'label' => __('Desktop', 'elemacy'),
                    ],
                    [
                        'value' => 'tablet',
                        'label' => __('Tablet', 'elemacy'),
                    ],
                    [
                        'value' => 'mobile',
                        'label' => __('Mobile', 'elemacy'),
                    ],
                ],
            ],
        ];
    }
}
