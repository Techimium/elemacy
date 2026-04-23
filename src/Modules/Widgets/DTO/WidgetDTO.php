<?php

namespace Elemacy\Modules\Widgets\DTO;

defined('ABSPATH') || exit;

use Elemacy\Core\DTO\DTO;

class WidgetDTO extends DTO
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $icon;

    /**
     * @var bool
     */
    public $is_enabled;

    /**
     * @var string
     */
    public $class;
}
