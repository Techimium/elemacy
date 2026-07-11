<?php

namespace Elemacy\TemplateLibrary\DTO;

defined('ABSPATH') || exit;

use Elemacy\Core\DTO\DTO;

class BlockTemplateListFilterDTO extends DTO
{
    /**
     * @var string|null
     */
    public $search;

    /**
     * @var string|null
     */
    public $type;

    /**
     * @var string|null
     */
    public $status;

    /**
     * @var int|string|null
     */
    public $page;

    /**
     * @var int|string|null
     */
    public $per_page;
}
