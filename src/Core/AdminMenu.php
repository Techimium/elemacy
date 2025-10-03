<?php

namespace Elemacy\Core;

class AdminMenu
{
    public function __construct()
    {
        add_action( 'admin_menu', [$this, 'register']);
    }

    public function register()
    {
        add_menu_page(
            'Elemacy',
            'Elemacy',
            'manage_options',
            'elemacy',
            [$this, 'render']
        );
    }

    public function render()
    {
        echo '<div id="elemacy_root"></div>';
    }
}
