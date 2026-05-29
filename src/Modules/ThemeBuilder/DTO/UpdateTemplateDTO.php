<?php

namespace Elemacy\Modules\ThemeBuilder\DTO;

defined('ABSPATH') || exit;

use Elemacy\Core\DTO\DTO;

class UpdateTemplateDTO extends DTO
{
    /**
     * @var int
     */
    public $id;

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

    /**
     * @var array
     */
    public $conditions;
}
