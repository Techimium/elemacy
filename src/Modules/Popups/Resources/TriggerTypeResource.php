<?php

namespace Elemacy\Modules\Popups\Resources;

defined('ABSPATH') || exit;

use Elemacy\Core\Capabilities\FeatureAvailability;
use Elemacy\Core\Resource;

/**
 * Serializes a single trigger (TriggerInterface) for the admin app.
 */
class TriggerTypeResource extends Resource
{
    public function to_array()
    {
        return [
            'value'         => $this->get_name(),
            'label'         => $this->get_label(),
            'is_mock'       => $this->is_mock(),
            'params_schema' => $this->get_config_schema(),
            // Every entry here came from TriggerManager::get_all(), so it is
            // by definition registered — "unknown" cannot occur; only
            // whether it is a real implementation or a non-functional Mock
            // stand-in (task 10.3: type-list resources expose availability
            // too, not just the per-saved-instance resources phase 4 covers).
            'availability'  => ($this->is_mock()
                ? FeatureAvailability::inactive_extension($this->get_name())
                : FeatureAvailability::available())->to_array(),
        ];
    }
}
