<?php

namespace Elemacy\Modules\Popups\Triggers;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Contracts\TriggerInterface;
use Elemacy\Modules\Popups\DTO\TriggerDTO;

abstract class BaseTrigger implements TriggerInterface
{
    public function get_config_schema(): array
    {
        return [];
    }

    public function to_js_config(TriggerDTO $dto): array
    {
        // The registered type must win regardless of anything already stored
        // in params (task 10.2): put it last so array_merge()'s "later key
        // wins" behavior can never let a params['type'] value masquerade as
        // a different trigger to the client engine.
        return array_merge(
            is_array($dto->params) ? $dto->params : [],
            ['type' => $this->get_name()]
        );
    }

    public function is_mock(): bool
    {
        return false;
    }
}
