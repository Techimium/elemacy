<?php

namespace Elemacy\Widgets;

defined('ABSPATH') || exit;

use Elemacy\Core\Admin\AdminMenu;
use Elemacy\Core\Admin\DTO\SubMenuDTO;
use Elemacy\Widgets\Services\EditorAssets;
use Elemacy\Widgets\Services\FrontendAssets;

/**
 * The always-on widget platform: registers every available widget with
 * Elementor, the Elemacy widget category, the general widgets' assets and the
 * Widgets screen.
 *
 * @internal
 */
class WidgetsBootstrap
{
    protected bool $hooked = false;

    private WidgetRegistry $registry;

    public function __construct(WidgetRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Attaches this class's WordPress hooks. Idempotent: a second call does
     * nothing.
     */
    public function register_hooks(): void
    {
        if ($this->hooked) {
            return;
        }

        $this->hooked = true;

        add_action('elementor/widgets/register', [$this->registry, 'register_with_elementor']);
        add_action('elementor/elements/categories_registered', [$this->registry, 'register_category']);
        new FrontendAssets();
        new EditorAssets();
        // After the modules' own pages (registered on init at 10), labels translated.
        add_action('init', [$this, 'add_menu'], 11);
    }

    public function add_menu(): void
    {
        $submenu_dto = new SubMenuDTO();
        $submenu_dto->page_title = __('Widgets', 'elemacy');
        $submenu_dto->menu_title = __('Widgets', 'elemacy');
        $submenu_dto->menu_slug = 'widgets';
        AdminMenu::add_submenu($submenu_dto);
    }
}
