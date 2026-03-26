<?php

namespace Elemacy\Core;

class AdminMenu
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register']);
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

        add_submenu_page(
            'elemacy',
            'Widgets',
            'Widgets',
            'manage_options',
            'elemacy#widgets',
            [$this, 'render']
        );

        add_submenu_page(
            'elemacy',
            'Modules',
            'Modules',
            'manage_options',
            'elemacy#modules',
            [$this, 'render']
        );

        remove_submenu_page('elemacy', 'elemacy');
    }

    public function render()
    {
        echo '<div id="elemacy_root"></div>';
    }
}
