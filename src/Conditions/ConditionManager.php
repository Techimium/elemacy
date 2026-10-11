<?php

namespace Elemacy\Conditions;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionInterface;
use Elemacy\Core\Capabilities\FeatureAvailability;
use Elemacy\Core\Capabilities\RegistryIdentifierGuard;

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

    public function register(ConditionInterface $condition): self
    {
        RegistryIdentifierGuard::assert_registrable(
            'Condition',
            $condition->get_name(),
            $this->conditions[$condition->get_name()] ?? null,
            $condition
        );

        $this->conditions[$condition->get_name()] = $condition;

        return $this;
    }

    public function get(string $name): ?ConditionInterface
    {
        return $this->conditions[$name] ?? null;
    }

    /**
     * Whether a stored condition identifier can actually run right now,
     * distinguishing an unregistered identifier (e.g. an extension that
     * used to provide it is gone) from one that only resolves to a
     * non-functional mock stand-in (e.g. the extension is present but
     * inactive) from a real, available condition.
     */
    public function get_availability(string $name): FeatureAvailability
    {
        $condition = $this->get($name);

        if (!$condition) {
            return FeatureAvailability::unknown_identifier($name);
        }

        if ($condition->is_mock()) {
            return FeatureAvailability::inactive_extension($name);
        }

        return FeatureAvailability::available();
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
