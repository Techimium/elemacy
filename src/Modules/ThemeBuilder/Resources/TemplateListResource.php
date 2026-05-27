<?php

namespace Elemacy\Modules\ThemeBuilder\Resources;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elemacy\Core\Resource;

class TemplateListResource extends Resource
{
    public function to_array()
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'status' => $this->status,
            'author' => $this->author,
            'date' => $this->date,
            'edit_with_elementor' => admin_url('post.php?post=' . $this->id . '&action=elementor'),
            'extras' => [],
        ];

        return apply_filters(
            Hooks::THEME_BUILDER_TEMPLATE_RESOURCE_FILTER,
            $data,
            $this->resource,
            'list'
        );
    }
}
