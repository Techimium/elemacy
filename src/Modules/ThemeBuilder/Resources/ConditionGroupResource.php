<?php

namespace Elemacy\Modules\ThemeBuilder\Resources;

defined('ABSPATH') || exit;

use Elemacy\Core\Resource;
use Elemacy\Modules\ThemeBuilder\Support\ConditionGroups;

/**
 * Serializes a condition group (type + its applicable conditions) for the admin app.
 */
class ConditionGroupResource extends Resource
{
    public function to_array()
    {
        return [
            'type'       => $this->type,
            'label'      => ConditionGroups::label($this->type),
            'conditions' => ConditionTypeResource::collection($this->conditions),
        ];
    }
}
