<?php

namespace Elemacy\Modules\Popups\Triggers\Mock;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Triggers\MockTrigger;

class ScrollToElement extends MockTrigger
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
