<?php

namespace Elemacy\Conditions\Resources;

defined('ABSPATH') || exit;

use Elemacy\Core\Capabilities\FeatureAvailability;
use Elemacy\Conditions\BaseCondition;
use Elemacy\Http\Resource;

/**
 * Serializes a single condition (ConditionInterface) for the admin app.
 */
class ConditionTypeResource extends Resource
{
    public function to_array()
    {
        return [
            'value'        => $this->get_name(),
            'label'        => $this->get_label(),
            'value_type'   => $this->get_value_type(),
            'sub_values'   => $this->get_sub_values(),
            'search'       => $this->get_search_config(),
            'is_mock'      => $this->is_mock(),
            // A rule for it must name a value; the admin form refuses one that
            // does not, as saving would (ConditionRepository).
            'requires_value' => $this->resource instanceof BaseCondition && $this->resource->requires_value(),
            // Every entry here came from ConditionManager::get_all(), so it
            // is by definition registered — "unknown" cannot occur; only
            // whether it is a real implementation or a non-functional Mock
            // stand-in (: type-list resources expose availability
            // too, not just the per-saved-instance resources phase 4 covers).
            'availability' => ($this->is_mock()
                ? FeatureAvailability::inactive_extension($this->get_name())
                : FeatureAvailability::available())->to_array(),
        ];
    }
}
