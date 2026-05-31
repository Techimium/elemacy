<?php

namespace Elemacy\Modules\Popups\DTO;

defined('ABSPATH') || exit;

use Elemacy\Core\DTO\DTO;

class PopupListFilterDTO extends DTO
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
