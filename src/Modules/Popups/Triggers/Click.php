<?php

namespace Elemacy\Modules\Popups\Triggers;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\DTO\TriggerDTO;

class Click extends BaseTrigger
{
    public function get_name(): string
    {
        return 'click';
    }

    public function get_label(): string
    {
        return __('On Click', 'elemacy');
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

    public function to_js_config(TriggerDTO $dto): array
    {
        $params = is_array($dto->params) ? $dto->params : [];

        return [
            'type'     => 'click',
            'selector' => (string) ($params['selector'] ?? ''),
        ];
    }
}
