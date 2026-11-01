<?php

namespace Elemacy\Modules\ThemeBuilder\DTO;

defined('ABSPATH') || exit;

use Elemacy\Http\DTO\DTO;

/**
 * @internal
 */
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
    /**
     * @var int|string|null
     */
    public $page;
    /**
     * @var int|string|null
     */
    public $per_page;
}
