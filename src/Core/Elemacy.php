<?php

namespace Elemacy\Core;

if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\Constants\OptionKeys;
use Elemacy\Core\DTO\MenuDTO;
use Elemacy\Core\DTO\SubMenuDTO;
use Elemacy\Core\Controls\Query\QueryControlManager;
use Elemacy\Core\Hooks;
use Elemacy\Core\Preview\PreviewAssets;
use Elemacy\Core\Preview\PreviewManager;
use Elemacy\Core\Rendering\TemplateAssetsRegistrar;
use Elemacy\Conditions\ConditionsBootstrap;
use Elemacy\Support\Brand;
use Elemacy\Support\Utils;
use Elemacy\TemplateLibrary\LibraryBootstrap;
use Elemacy\TemplateLibrary\LibraryPostType;
use Elemacy\TemplateLibrary\TypeDefinition;
use Elemacy\TemplateLibrary\TypeRegistry;

class Elemacy
{
    protected static $instance = null;
    protected ModuleManager $module_manager;

    protected function __construct()
    {
        $this->activation_actions();
        add_action('plugins_loaded', [$this, 'init']);
    }

    public static function get_instance(): self
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function activation_actions()
    {
        register_activation_hook(ELEMACY_FILE, function ($network_wide = false) {
            if (is_multisite() && $network_wide) {
                // number => 0 lifts WP_Site_Query's default 100-site cap.
                $site_ids = get_sites([
                    'fields' => 'ids',
                    'number' => 0,
                ]);

                foreach ($site_ids as $site_id) {
                    switch_to_blog((int) $site_id);
                    $this->seed_site();
                    restore_current_blog();
                }

                return;
            }

            $this->seed_site(true);
        });

        // Sites created after a network activation need the same seeding.
        add_action('wp_initialize_site', function ($new_site) {
            if (!function_exists('is_plugin_active_for_network')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            if (!is_plugin_active_for_network(ELEMACY_PLUGIN_BASE)) {
                return;
            }

            switch_to_blog((int) $new_site->blog_id);
            $this->seed_site();
            restore_current_blog();
        }, 10, 1);
    }

    /**
     * Seeds one site's defaults. `flush_rewrite_rules()` is unreliable inside a
     * switched blog, so network paths drop the `rewrite_rules` option instead —
     * that site regenerates them (with the library CPT registered) on its next
     * request.
     */
    protected function seed_site(bool $flush_now = false): void
    {
        add_option(OptionKeys::ACTIVE_MODULES, [
            'theme-builder',
            'widgets',
            'custom-css',
            'dynamic-tags',
            'popups',
        ]);

        if ($flush_now) {
            LibraryPostType::register_post_type();
            flush_rewrite_rules(false);
        } else {
            delete_option('rewrite_rules');
        }
    }

    public function init()
    {
        if (!$this->check_requirements()) {
            return;
        }

        $this->load_textdomain();
        $this->handle_version_update();
        $this->init_core_components();
        $this->init_module_manager();
        $this->load_modules();
        $this->init_admin_menus();
        $this->init_modules();
        $this->register_block_library_type();
        $this->register_library_types();
        $this->init_routes();
        $this->register_rest_routes();
        $this->register_ajax_routes();
        $this->register_admin_menus();

        do_action(Hooks::LOADED_ACTION);
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('elemacy', false, dirname(ELEMACY_PLUGIN_BASE) . '/languages');
    }

    public function handle_version_update()
    {
        $installed_version = get_option(OptionKeys::DB_VERSION, false);

        if ($installed_version === ELEMACY_VERSION) {
            return;
        }

        if ($installed_version === false) {
            update_option(OptionKeys::DB_VERSION, ELEMACY_VERSION);
            return;
        }

        if (version_compare($installed_version, ELEMACY_VERSION, '<')) {
            (new Migrator())->run((string) $installed_version, ELEMACY_VERSION);
        }

        update_option(OptionKeys::DB_VERSION, ELEMACY_VERSION);

        do_action(Hooks::VERSION_UPDATED_ACTION, (string) $installed_version, ELEMACY_VERSION);
    }

    public function init_core_components()
    {
        new AdminScripts();
        new FrontendScripts();
        new PluginLinks();
        new ConditionsBootstrap();
        new LibraryBootstrap();
        new PreviewManager();
        new PreviewAssets();
        new QueryControlManager();
        (new TemplateAssetsRegistrar())->register_hooks();
    }

    public function init_module_manager()
    {
        $this->module_manager = new ModuleManager();
    }

    public function get_module_manager(): ModuleManager
    {
        return $this->module_manager;
    }

    protected function load_modules()
    {
        $modules = require_once ELEMACY_PATH . 'src/Config/modules.php';

        foreach ($modules as $module_class) {
            if (!class_exists($module_class)) {
                continue;
            }

            $module = new $module_class();

            if (is_subclass_of($module, Module::class)) {
                $this->module_manager->register($module);
            }
        }

        do_action(Hooks::REGISTER_MODULES_ACTION, $this->module_manager);
    }

    protected function init_modules()
    {
        $this->module_manager->init_modules();
    }

    /**
     * Fire the library-types registration hook on `init` (priority 20) — after
     * modules register their own types at the default priority — so add-ons can
     * contribute more, all after the textdomain is available (never on
     * plugins_loaded, which would load translations too early).
     */
    protected function register_library_types()
    {
        add_action('init', static function () {
            do_action(Hooks::LIBRARY_TYPES_REGISTER_ACTION, TypeRegistry::instance());
        }, 20);
    }

    /**
     * Register the built-in generic "block" library type owned by Core. Block
     * items are referenced by ID rather than auto-resolved, so the library always
     * has a default type even with every module off; modules push their own
     * specialized types (e.g. Widgets → "Loop Item"). Priority 5 keeps the generic
     * type first in the registry so it is the default selection. Deferred to
     * `init` because the label is translated.
     */
    protected function register_block_library_type()
    {
        add_action('init', static function () {
            TypeRegistry::instance()->register(
                new TypeDefinition('section', __('Section', 'elemacy'), 'block', 'core')
            );
        }, 5);
    }

    protected function init_routes()
    {
        Route::set_namespace('elemacy');
        require_once ELEMACY_PATH . 'src/Config/api.php';
    }

    protected function init_admin_menus()
    {
        add_action('init', function () {
            $plugin_menu_dto = new MenuDTO();
            $plugin_menu_dto->page_title = __('Elemacy', 'elemacy');
            $plugin_menu_dto->menu_title = __('Elemacy', 'elemacy');
            $plugin_menu_dto->menu_slug = 'elemacy';
            $plugin_menu_dto->icon_url = Brand::data_uri();
            AdminMenu::add_menu($plugin_menu_dto);

            $overview_menu_dto = new SubMenuDTO();
            $overview_menu_dto->page_title = __('Overview', 'elemacy');
            $overview_menu_dto->menu_title = __('Overview', 'elemacy');
            $overview_menu_dto->menu_slug = '';
            AdminMenu::add_submenu($overview_menu_dto);

            $modules_menu_dto = new SubMenuDTO();
            $modules_menu_dto->page_title = __('Modules', 'elemacy');
            $modules_menu_dto->menu_title = __('Modules', 'elemacy');
            $modules_menu_dto->menu_slug = 'modules';
            AdminMenu::add_submenu($modules_menu_dto);

            $library_menu_dto = new SubMenuDTO();
            $library_menu_dto->page_title = __('Template Library', 'elemacy');
            $library_menu_dto->menu_title = __('Template Library', 'elemacy');
            $library_menu_dto->menu_slug = 'library';
            AdminMenu::add_submenu($library_menu_dto);

            if (!Utils::is_pro_active()) {
                $upgrade_menu_dto = new SubMenuDTO();
                $upgrade_menu_dto->page_title = __('Upgrade to Pro', 'elemacy');
                $upgrade_menu_dto->menu_title = '<span class="elemacy-external-menu-link">' . esc_html__('Upgrade to Pro', 'elemacy') . '</span>';
                $upgrade_menu_dto->external_url = 'https://elemacy.com';
                $upgrade_menu_dto->position = 999;
                AdminMenu::add_submenu($upgrade_menu_dto);
            }
        });
    }

    protected function register_rest_routes()
    {
        add_action('rest_api_init', function () {
            foreach (Route::get_routes() as $route) {
                $route->register();
            }
        });
    }

    protected function register_ajax_routes()
    {
        add_action('init', function () {
            AjaxRouter::register();
        });
    }

    protected function register_admin_menus()
    {
        add_action('admin_menu', function () {
            AdminMenu::register();
        });
    }

    public function check_requirements(): bool
    {
        return CompatibilityChecker::instance()->check();
    }

    public static function boot()
    {
        return static::get_instance();
    }
}
