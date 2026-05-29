<?php

namespace Elemacy\Modules\ThemeBuilder\Resources;

defined('ABSPATH') || exit;

use Elemacy\Core\Resource;

/**
 * Serializes a single condition (ConditionInterface) for the admin app.
 */
class ConditionTypeResource extends Resource
{
    public function to_array()
    {
        return [
            'value'      => $this->get_name(),
            'label'      => $this->get_label(),
            'has_value'  => $this->has_value(),
            'sub_values' => $this->get_sub_values(),
            'is_mock'    => $this->is_mock(),
        ];
    }
}
