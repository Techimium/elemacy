<?php

namespace Elemacy\Modules\Popups\Triggers\Mock;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Support\PopupChoices;
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

    /**
     * The parameter keys must match elemacy-pro's OnPopupClose exactly (pinned
     * by a Pro test), so a popup saved with Pro active keeps them without it.
     */
    public function get_config_schema(): array
    {
        return [
            [
                'key'     => 'popup_id',
                'label'   => __('After this popup closes', 'elemacy'),
                'type'    => 'select',
                'default' => PopupChoices::NONE,
                'options' => PopupChoices::options(__('Any other popup', 'elemacy')),
            ],
        ];
    }
}
