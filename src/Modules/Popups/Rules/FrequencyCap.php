<?php

namespace Elemacy\Modules\Popups\Rules;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\DTO\RuleDTO;

class FrequencyCap extends BaseRule
{
    public function get_name(): string
    {
        return 'frequency_cap';
    }

    public function get_label(): string
    {
        return __('Show up to X times', 'elemacy');
    }

    public function get_config_schema(): array
    {
        return [
            [
                'key'     => 'max',
                'label'   => __('Maximum displays', 'elemacy'),
                'type'    => 'number',
                'default' => 3,
                'min'     => 1,
            ],
        ];
    }

    public function to_js_config(RuleDTO $dto): array
    {
        $params = is_array($dto->params) ? $dto->params : [];

        return [
            'type' => 'frequency_cap',
            'max'  => (int) ($params['max'] ?? 3),
        ];
    }
}
