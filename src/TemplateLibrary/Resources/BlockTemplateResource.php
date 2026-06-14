<?php

namespace Elemacy\TemplateLibrary\Resources;

defined('ABSPATH') || exit;

use Elemacy\Core\Resource;

class BlockTemplateResource extends Resource
{
    public function to_array()
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'status' => $this->status,
            'author' => $this->author,
            'date' => $this->date,
            'edit_with_elementor' => admin_url('post.php?post=' . $this->id . '&action=elementor'),
        ];
    }
}
