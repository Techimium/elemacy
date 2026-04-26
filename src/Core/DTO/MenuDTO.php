<?php

namespace Elemacy\Core\DTO;

defined('ABSPATH') || exit;

class MenuDTO extends DTO
{
    /**
     * @var string
     */
    public $page_title;

    /**
     * @var string
     */
    public $menu_title;

    /**
     * @var string
     */
    public $capability = 'manage_options';

    /**
     * @var string
     */
    public $menu_slug;

    /**
     * @var callable|null
     */
    public $callback = null;

    /**
     * @var string
     */
    public $icon_url = '';

    /**
     * @var int|null
     */
    public $position = null;
}