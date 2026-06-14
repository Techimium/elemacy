<?php

namespace Elemacy\TemplateLibrary\DTO;

defined('ABSPATH') || exit;

use Elemacy\Core\DTO\DTO;

class BlockTemplateDTO extends DTO
{
    public $id;
    public $title;
    public $type;
    public $status;
    public $author;
    public $date;
}
