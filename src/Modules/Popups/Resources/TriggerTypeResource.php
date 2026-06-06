<?php

namespace Elemacy\Modules\Popups\Resources;

defined('ABSPATH') || exit;

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
        ];
    }
}
