<?php

namespace Elemacy\Modules\ThemeBuilder\DTO;

defined('ABSPATH') || exit;

use Elemacy\Http\DTO\DTO;

/**
 * @internal
 */
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
    /**
     * @var array
     */
    public $conditions;
}
