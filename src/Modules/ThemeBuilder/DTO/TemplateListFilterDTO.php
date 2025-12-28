<?php

namespace Elemacy\Modules\ThemeBuilder\DTO;

use Elemacy\Core\DTO\DTO;

class TemplateListFilterDTO extends DTO
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
}
