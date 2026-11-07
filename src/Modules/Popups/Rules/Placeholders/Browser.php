<?php

namespace Elemacy\Modules\Popups\Rules\Placeholders;

defined('ABSPATH') || exit;


/**
 * @internal
 */
class Browser extends PlaceholderRule
{
    public function get_name(): string
    {
        return 'browser';
    }

    public function get_label(): string
    {
        return __('By browser', 'elemacy');
    }

    public function get_config_schema(): array
    {
        return [
            [
                'key'     => 'browsers',
                'label'   => __('Browsers', 'elemacy'),
                'type'    => 'checkbox-group',
                'default' => [],
                'options' => [
                    [
                        'value' => 'chrome',
                        'label' => __('Chrome', 'elemacy'),
                    ],
                    [
                        'value' => 'firefox',
                        'label' => __('Firefox', 'elemacy'),
                    ],
                    [
                        'value' => 'safari',
                        'label' => __('Safari', 'elemacy'),
                    ],
                    [
                        'value' => 'edge',
                        'label' => __('Edge', 'elemacy'),
                    ],
                ],
            ],
        ];
    }
}
