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

        
        add_submenu_page(
            'elemacy',
            'Overview',
            'Overview',
            'manage_options',
            'elemacy#',
            [$this, 'render']
        );
        
        add_submenu_page(
            'elemacy',
            'Theme Builder',
            'Theme Builder',
            'manage_options',
            'elemacy#theme-builder',
            [$this, 'render']
        );
    }

    public function render()
    {
        echo '<div id="elemacy_root"></div>';
    }
}
