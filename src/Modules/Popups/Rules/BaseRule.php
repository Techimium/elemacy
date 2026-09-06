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
        return array_merge(
            ['type' => $this->get_name()],
            is_array($dto->params) ? $dto->params : []
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
