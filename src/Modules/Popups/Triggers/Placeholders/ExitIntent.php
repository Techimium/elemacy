<?php

namespace Elemacy\Modules\Popups\Triggers\Placeholders;

defined('ABSPATH') || exit;


/**
 * @internal
 */
class ExitIntent extends PlaceholderTrigger
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
