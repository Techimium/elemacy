<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Triggers\TriggerInterface;

class TriggerManager
{
    protected static ?self $instance = null;

    /** @var TriggerInterface[] */
    protected array $triggers = [];

    public static function instance(): self
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function register(TriggerInterface $trigger): static
    {
        $this->triggers[$trigger->get_name()] = $trigger;

        return $this;
    }

    public function get(string $name): ?TriggerInterface
    {
        return $this->triggers[$name] ?? null;
    }

    /** @return TriggerInterface[] */
    public function get_all(): array
    {
        return $this->triggers;
    }
}
