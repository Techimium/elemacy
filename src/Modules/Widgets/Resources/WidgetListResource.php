<?php

namespace Elemacy\Modules\Widgets\Resources;

use Elemacy\Core\Resource;

class WidgetListResource extends Resource
{
    public function to_array()
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'icon' => $this->icon,
            'is_enabled' => $this->is_enabled,
        ];
    }
}
