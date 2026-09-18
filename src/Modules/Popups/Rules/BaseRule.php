<?php

namespace Elemacy\Modules\Popups\Rules;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Contracts\RuleInterface;
use Elemacy\Modules\Popups\DTO\RuleDTO;

abstract class BaseRule implements RuleInterface
{
    public function get_config_schema(): array
    {
        return [];
    }

    public function to_js_config(RuleDTO $dto): array
    {
        // The registered type must win regardless of anything already stored
        // in params (task 10.2): put it last so array_merge()'s "later key
        // wins" behavior can never let a params['type'] value masquerade as
        // a different rule to the client engine.
        return array_merge(
            is_array($dto->params) ? $dto->params : [],
            ['type' => $this->get_name()]
        );
    }

    public function check(RuleDTO $dto): bool
    {
        return true;
    }

    public function is_server_evaluable(): bool
    {
        return false;
    }

    public function is_mock(): bool
    {
        return false;
    }
}
