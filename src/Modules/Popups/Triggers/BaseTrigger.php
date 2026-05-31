<?php

namespace Elemacy\Modules\Popups\Triggers;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\DTO\TriggerDTO;

abstract class BaseTrigger implements TriggerInterface
{
    public function get_config_schema(): array
    {
        return [];
    }

    public function to_js_config(TriggerDTO $dto): array
    {
        return array_merge(
            ['type' => $this->get_name()],
            is_array($dto->params) ? $dto->params : []
        );
    }

    public function is_mock(): bool
    {
        return false;
    }
}
