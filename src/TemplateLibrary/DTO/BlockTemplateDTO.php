<?php

namespace Elemacy\TemplateLibrary\DTO;

defined('ABSPATH') || exit;

use Elemacy\Http\DTO\DTO;

/**
 * @internal
 */
class BlockTemplateDTO extends DTO
{
    public $id;
    public $title;
    public $type;
    public $status;
    public $author;
    public $date;
}
