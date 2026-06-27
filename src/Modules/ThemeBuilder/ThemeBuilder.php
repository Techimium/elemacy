<?php
namespace Elemacy\Modules\ThemeBuilder;

if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\AdminMenu;
use Elemacy\Core\DTO\SubMenuDTO;
use Elemacy\Core\Hooks;
use Elemacy\Core\Module;
use Elemacy\Modules\ThemeBuilder\Documents\DocumentManager;
use Elemacy\Modules\ThemeBuilder\Services\LocationRegistry;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;
use Elemacy\Support\Utils;
use Elemacy\TemplateLibrary\TypeDefinition;
use Elemacy\TemplateLibrary\TypeRegistry;

class ThemeBuilder extends Module
{
    public function get_name(): string
    {
        return 'theme-builder';
    }

    public function get_title(): string
    {
        return __('Theme Builder', 'elemacy');
    }

    public function get_icon(): string
    {
        return 'layers';
    }

    public function get_description(): string
    {
        return __('Design headers, footers, archives and other site-wide templates that apply across your theme.', 'elemacy');
    }

    public function init(): void
    {
        $this->register_admin_menu();
        $this->register_types();
        $this->register_locations();
        new DocumentManager();
        ThemeBuilderManager::instance()->register_hooks();
    }

    /**
     * The template types this module owns. The CPT itself is registered by Core.
     * Hooked on `init` because the labels are translated — calling __() before
     * `init` trips WP 6.7's "translation loaded too early" notice.
     */
    protected function register_types(): void
    {
        add_action('init', function () {
            $registry = TypeRegistry::instance();

            $types = [
                'header'  => __('Header', 'elemacy'),
                'footer'  => __('Footer', 'elemacy'),
                'single'  => __('Single', 'elemacy'),
                'archive' => __('Archive', 'elemacy'),
                '404'     => __('404 Page', 'elemacy'),
                'search'  => __('Search Results', 'elemacy'),
            ];

            foreach ($types as $name => $label) {
                $registry->register(new TypeDefinition($name, $label, 'theme', $this->get_name()));
            }
        });
    }

    /**
     * Map requests to the content template type. Specific locations get lower
     * priority numbers so they win over generic ones; pro can add more via
     * THEME_BUILDER_LOCATIONS_REGISTER_ACTION.
     */
    protected function register_locations(): void
    {
        $registry = LocationRegistry::instance();

        $registry->register('404', static fn (): bool => is_404(), 5);
        $registry->register('single', static fn (): bool => is_singular(), 10);
        $registry->register('search', static fn (): bool => is_search(), 15);
        $registry->register('archive', static fn (): bool => is_archive() || is_home(), 20);

        do_action(Hooks::THEME_BUILDER_LOCATIONS_REGISTER_ACTION, $registry);
    }

    public function register_routes()
    {
        require_once Utils::get_plugin_path('src/Modules/ThemeBuilder/Config/api.php');
    }

    public function register_admin_menu()
    {
        add_action('init', function () {
            $submenu_dto = new SubMenuDTO();
            $submenu_dto->page_title = __('Theme Builder', 'elemacy');
            $submenu_dto->menu_title = __('Theme Builder', 'elemacy');
            $submenu_dto->menu_slug = 'theme-builder';
            AdminMenu::add_submenu($submenu_dto);
        });
    }
}
