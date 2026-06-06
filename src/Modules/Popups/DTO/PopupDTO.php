<?php

namespace Elemacy\Modules\Popups\DTO;

defined('ABSPATH') || exit;

use Elemacy\Core\DTO\DTO;

class PopupDTO extends DTO
{
    public $id;
    public $title;
    public $type;
    public $status;
    public $author;
    public $date;

    /** @var \Elemacy\Conditions\DTO\ConditionRuleDTO[] */
    public $conditions = [];

    /** @var TriggerDTO[] */
    public $triggers = [];

    /** @var RuleDTO[] */
    public $rules = [];
}
