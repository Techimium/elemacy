<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Core\Capabilities\FeatureAvailability;
use Elemacy\Core\Capabilities\RegistryIdentifierGuard;
use Elemacy\Modules\Popups\Contracts\TriggerInterface;

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

    public function register(TriggerInterface $trigger): self
    {
        RegistryIdentifierGuard::assert_registrable(
            'Popup trigger',
            $trigger->get_name(),
            $this->triggers[$trigger->get_name()] ?? null,
            $trigger
        );

        $this->triggers[$trigger->get_name()] = $trigger;

        return $this;
    }

    public function get(string $name): ?TriggerInterface
    {
        return $this->triggers[$name] ?? null;
    }

    /**
     * Whether a stored trigger identifier can actually fire right now: an
     * unavailable trigger (unregistered, or only a non-functional mock
     * stand-in) has no real client handler, so it must not be shipped to the
     * browser as though it were a live way to open the popup.
     */
    public function get_availability(string $name): FeatureAvailability
    {
        $trigger = $this->get($name);

        if (!$trigger) {
            return FeatureAvailability::unknown_identifier($name);
        }

        if ($trigger->is_mock()) {
            return FeatureAvailability::inactive_extension($name);
        }

        return FeatureAvailability::available();
    }

    /** @return TriggerInterface[] */
    public function get_all(): array
    {
        return $this->triggers;
    }
}
