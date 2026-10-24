<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Core\Capabilities\FeatureAvailability;
use Elemacy\Core\Capabilities\RegistryIdentifierGuard;
use Elemacy\Modules\Popups\Contracts\RuleInterface;

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
        RegistryIdentifierGuard::assert_registrable(
            'Popup rule',
            $rule->get_name(),
            $this->rules[$rule->get_name()] ?? null,
            $rule
        );

        $this->rules[$rule->get_name()] = $rule;

        return $this;
    }

    public function get(string $name): ?RuleInterface
    {
        return $this->rules[$name] ?? null;
    }

    /**
     * Whether a stored rule identifier can actually run right now, whether it
     * is enforced server-side or purely by the client engine: an unavailable
     * rule (unregistered, or only a non-functional mock stand-in) cannot be
     * relied on by either side to gate the popup, so neither treats it as
     * satisfied.
     */
    public function get_availability(string $name): FeatureAvailability
    {
        $rule = $this->get($name);

        if (!$rule) {
            return FeatureAvailability::unknown_identifier($name);
        }

        if ($rule->is_mock()) {
            return FeatureAvailability::inactive_extension($name);
        }

        return FeatureAvailability::available();
    }

    /** @return RuleInterface[] */
    public function get_all(): array
    {
        return $this->rules;
    }
}
