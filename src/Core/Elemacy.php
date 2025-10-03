<?php

namespace Elemacy\Core;

class Elemacy
{
    public function __construct()
    {
        $this->init_modules();
    }

    public function init_modules()
    {
        new AdminMenu();
        new AdminScripts();
    }

    public static function boot()
    {
        return new static();
    }
}
