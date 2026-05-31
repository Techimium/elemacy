<?php

namespace Elemacy\Modules\Popups\Resources;

defined('ABSPATH') || exit;

use Elemacy\Core\Resource;

class PopupListResource extends Resource
{
    public function to_array()
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'status' => $this->status,
            'date' => $this->date,
            'edit_with_elementor' => admin_url('post.php?post=' . $this->id . '&action=elementor'),
        ];
    }
}
