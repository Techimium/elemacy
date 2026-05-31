<?php

namespace Elemacy\Modules\Popups\Triggers\Mock;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Triggers\MockTrigger;

class OnPopupClose extends MockTrigger
{
    public function get_name(): string
    {
        return 'on_popup_close';
    }

    public function get_label(): string
    {
        return __('After Another Popup Closes', 'elemacy');
    }

    public function get_config_schema(): array
    {
        return [];
    }
}
