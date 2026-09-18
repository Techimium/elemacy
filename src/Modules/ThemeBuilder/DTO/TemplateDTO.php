<?php

namespace Elemacy\Modules\ThemeBuilder\DTO;

defined('ABSPATH') || exit;

use Elemacy\Http\DTO\DTO;

class TemplateDTO extends DTO
{
    public $id;
    public $title;
    public $type;
    public $status;
    public $author;
    public $date;

    /** @var ConditionRuleDTO[] */
    public $conditions = [];
}
