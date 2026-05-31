<?php

namespace Elemacy\Modules\Popups\Resources;

defined('ABSPATH') || exit;

use Elemacy\Core\Resource;

/**
 * Serializes a single advanced rule (RuleInterface) for the admin app.
 */
class RuleTypeResource extends Resource
{
    public function to_array()
    {
        return [
            'value'               => $this->get_name(),
            'label'               => $this->get_label(),
            'is_mock'             => $this->is_mock(),
            'is_server_evaluable' => $this->is_server_evaluable(),
            'params_schema'       => $this->get_config_schema(),
        ];
    }
}
