<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Rules\RuleInterface;

class RuleManager
{
    protected static ?self $instance = null;

    /** @var RuleInterface[] */
    protected array $rules = [];

    public static function instance(): self
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function register(RuleInterface $rule): self
    {
        $this->rules[$rule->get_name()] = $rule;

        return $this;
    }

    public function get(string $name): ?RuleInterface
    {
        return $this->rules[$name] ?? null;
    }

    /** @return RuleInterface[] */
    public function get_all(): array
    {
        return $this->rules;
    }
}
