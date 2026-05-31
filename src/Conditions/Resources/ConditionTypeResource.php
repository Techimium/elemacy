<?php

namespace Elemacy\Conditions\Resources;

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
            'value_type' => $this->get_value_type(),
            'sub_values' => $this->get_sub_values(),
            'search'     => $this->get_search_config(),
            'is_mock'    => $this->is_mock(),
        ];
    }
}
