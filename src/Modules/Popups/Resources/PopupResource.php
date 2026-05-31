<?php

namespace Elemacy\Modules\Popups\Resources;

defined('ABSPATH') || exit;

use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Core\Resource;
use Elemacy\Modules\Popups\DTO\RuleDTO;
use Elemacy\Modules\Popups\DTO\TriggerDTO;

class PopupResource extends Resource
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
            'conditions' => ConditionRuleDTO::to_arrays($this->conditions ?? []),
            'triggers' => TriggerDTO::to_arrays($this->triggers ?? []),
            'rules' => RuleDTO::to_arrays($this->rules ?? []),
            'analytics' => $this->analytics,
        ];
    }
}
