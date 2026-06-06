<?php

namespace Elemacy\Modules\Popups\Triggers;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\DTO\TriggerDTO;

class PageLoad extends BaseTrigger
{
    public function get_name(): string
    {
        return 'page_load';
    }

    public function get_label(): string
    {
        return __('On Page Load', 'elemacy');
    }

    public function get_config_schema(): array
    {
        return [
            [
                'key'     => 'delay',
                'label'   => __('Delay (seconds)', 'elemacy'),
                'type'    => 'number',
                'default' => 0,
                'min'     => 0,
            ],
        ];
    }

    public function to_js_config(TriggerDTO $dto): array
    {
        $params = is_array($dto->params) ? $dto->params : [];

        return [
            'type'  => 'page_load',
            'delay' => (int) ($params['delay'] ?? 0),
        ];
    }
}
