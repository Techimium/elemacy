<?php

namespace Elemacy\Widgets\Resources;

defined('ABSPATH') || exit;

use Elemacy\Http\Resource;

/**
 * @internal
 */
class WidgetListResource extends Resource
{
    public function to_array()
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'icon' => $this->icon,
            'description' => $this->description,
            'is_enabled' => $this->is_enabled,
        ];
    }
}
