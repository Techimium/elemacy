<?php

namespace Elemacy\Modules\Popups\Triggers\Mock;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Triggers\MockTrigger;

class ExitIntent extends MockTrigger
{
    public function get_name(): string
    {
        return 'exit_intent';
    }

    public function get_label(): string
    {
        return __('On Exit Intent', 'elemacy');
    }

    public function get_config_schema(): array
    {
        return [];
    }
}
