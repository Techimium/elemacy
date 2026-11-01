<?php

namespace Elemacy\Modules\Popups\Triggers\Placeholders;

defined('ABSPATH') || exit;


/**
 * @internal
 */
class ScrollToElement extends PlaceholderTrigger
{
    public function get_name(): string
    {
        return 'scroll_to_element';
    }

    public function get_label(): string
    {
        return __('On Scroll To Element', 'elemacy');
    }

    public function get_config_schema(): array
    {
        return [
            [
                'key'     => 'selector',
                'label'   => __('CSS selector', 'elemacy'),
                'type'    => 'text',
                'default' => '',
            ],
        ];
    }
}
