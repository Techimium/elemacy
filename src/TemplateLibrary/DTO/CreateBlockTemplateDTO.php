<?php

namespace Elemacy\TemplateLibrary\DTO;

defined('ABSPATH') || exit;

use Elemacy\Core\DTO\DTO;

class CreateBlockTemplateDTO extends DTO
{
    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $status;
}
