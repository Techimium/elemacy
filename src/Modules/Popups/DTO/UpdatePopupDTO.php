<?php

namespace Elemacy\Modules\Popups\DTO;

defined('ABSPATH') || exit;

use Elemacy\Core\DTO\DTO;

class UpdatePopupDTO extends DTO
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

    /**
     * @var array
     */
    public $triggers;

    /**
     * @var array
     */
    public $rules;
}
