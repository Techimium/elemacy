<?php

namespace Elemacy\Conditions;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionInterface;

class ConditionManager
{
    protected static ?self $instance = null;

    /** @var ConditionInterface[] */
    protected array $conditions = [];

    public static function instance(): self
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function register(ConditionInterface $condition): static
    {
        $this->conditions[$condition->get_name()] = $condition;

        return $this;
    }

    public function get(string $name): ?ConditionInterface
    {
        return $this->conditions[$name] ?? null;
    }

    /** @return ConditionInterface[] */
    public function get_all(): array
    {
        return $this->conditions;
    }

    /**
     * Returns conditions grouped by their type.
     *
     * @return array<string, ConditionInterface[]>
     */
    public function get_grouped(): array
    {
        $groups = [];

        foreach ($this->conditions as $condition) {
            $groups[$condition->get_type()][] = $condition;
        }

        return $groups;
    }
}
