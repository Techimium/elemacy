<?php

namespace Elemacy\Conditions\Resources;

defined('ABSPATH') || exit;

use Elemacy\Core\Resource;
use Elemacy\Conditions\Support\ConditionGroups;

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
