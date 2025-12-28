<?php

namespace Elemacy\Modules\ThemeBuilder\DTO;

use Elemacy\Core\DTO\DTO;

class CreateTemplateDTO extends DTO
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
