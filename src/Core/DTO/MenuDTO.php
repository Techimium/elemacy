<?php

namespace Elemacy\Core\DTO;

class MenuDTO extends DTO
{
    public $page_title;
    public $menu_title;
    public $capabilty = 'manage_options';
    public $menu_slug;
    public $callback = null;
    public $icon_url = '';
    public $position = null;
}