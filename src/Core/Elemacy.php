<?php

namespace Elemacy\Core;

if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\Constants\OptionKeys;
use Elemacy\Core\Contracts\ServiceProviderInterface;
use Elemacy\Core\Admin\MenuDTO;
use Elemacy\Core\Admin\SubMenuDTO;
use Elemacy\Core\Multisite\SiteBatchIterator;
use Elemacy\Core\Providers\CoreServiceProvider;
use Elemacy\Core\Hooks;
use Elemacy\Support\Brand;
use Elemacy\Support\Utils;
use Elemacy\TemplateLibrary\LibraryPostType;
use Elemacy\TemplateLibrary\TypeDefinition;
use Elemacy\TemplateLibrary\TypeRegistry;
use Elemacy\Http\AjaxRouter;
use Elemacy\Http\Route;
use Elemacy\Core\Admin\AdminMenu;

class Elemacy
{
    protected static $instance = null;
    protected ModuleManager $module_manager;
    protected ?ModuleCatalog $module_catalog = null;

    /** @var ServiceProviderInterface[] */
    protected array $providers = [];

    /**
     * Providers whose boot() has already run this request. Identity-keyed,
     * not class-keyed: two instances of one provider class are two distinct
     * contributions and each boots once.
     *
     * @var \SplObjectStorage<ServiceProviderInterface, null>
     */
    protected \SplObjectStorage $booted_providers;

    /**
     * Guards the whole bootstrap, not only the provider phase: every step in
     * init — provider registration/boot, module loading, route/admin- menu
     * registration — assumes it runs exactly once per request.
     *
     * @var bool
     */
    protected bool $initialized = false;

    protected function __construct()
    {
        $this->booted_providers = new \SplObjectStorage();

        $this->activation_actions();
        $this->subscribe_own_provider();
        add_action('plugins_loaded', [$this, 'init']);
    }

    /**
     * Free registers its own core provider through the same
     * REGISTER_PROVIDERS_ACTION extension point an add-on would use,
     * proving the mechanism works uniformly rather than special-casing
     * Free's own services — subscribed here (the constructor, which runs
     * as soon as this file's plugin bootstrap requires it) so it fires
     * whenever init() later does, the same way Pro's own subscription does.
     */
    protected function subscribe_own_provider(): void
    {
        add_action(Hooks::REGISTER_PROVIDERS_ACTION, function (self $elemacy) {
            $elemacy->register_provider(new CoreServiceProvider());
        });
    }

    /**
     * Queues a provider for the current request's register()/boot() cycle.
     * Called by REGISTER_PROVIDERS_ACTION listeners (Free's own subscriber
     * above, and any add-on's), never directly by application code.
     */
    public function register_provider(ServiceProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    public static function get_instance(): self
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * The site-count-independent option a bounded network activation leaves
     * behind when more sites remain than fit in one batch.
     */
    public const NETWORK_SEED_OFFSET_OPTION = 'elemacy_network_seed_offset';

    public const NETWORK_SEED_BATCH_SIZE = 100;

    public function activation_actions()
    {
        register_activation_hook(ELEMACY_FILE, function ($network_wide = false) {
            if (is_multisite() && $network_wide) {
                update_site_option(self::NETWORK_SEED_OFFSET_OPTION, 0);
                $this->continue_network_seeding();

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

            try {
                $this->seed_site();
            } finally {
                restore_current_blog();
            }
        }, 10, 1);

        // Resumes a bounded network seed a prior activation request did not
        // finish, a small amount of work per request until exhausted —
        // never a second unbounded pass.
        add_action('plugins_loaded', [$this, 'maybe_continue_network_seeding'], 1);
    }

    /**
     * @internal Public only so it can be hooked directly to plugins_loaded;
     * not part of this class's supported API.
     */
    public function maybe_continue_network_seeding(): void
    {
        if (!is_multisite() || get_site_option(self::NETWORK_SEED_OFFSET_OPTION, false) === false) {
            return;
        }

        $this->continue_network_seeding();
    }

    protected function continue_network_seeding(): void
    {
        $offset = (int) get_site_option(self::NETWORK_SEED_OFFSET_OPTION, 0);

        $result = (new SiteBatchIterator())->process_batch(
            function (): void {
                $this->seed_site();
            },
            $offset,
            self::NETWORK_SEED_BATCH_SIZE
        );

        if ($result['has_more']) {
            update_site_option(self::NETWORK_SEED_OFFSET_OPTION, $offset + $result['processed']);
        } else {
            delete_site_option(self::NETWORK_SEED_OFFSET_OPTION);
        }
    }

    /**
     * The safety net under both the bounded batch above and
     * wp_initialize_site: a site that is somehow still unseeded when it
     * receives its own real request (the batch has not reached it yet, or
     * predates this mechanism entirely) seeds itself here, once, on that
     * request — no coordination with the network-wide job required.
     */
    protected function maybe_seed_current_site(): void
    {
        if (get_option(OptionKeys::ACTIVE_MODULES, null) !== null) {
            return;
        }

        // Unlike seed_site(), no rewrite-rules flush: by the time a site
        // reaches its own normal request, the library CPT is already
        // registered through this plugin's ordinary per-request bootstrap
        // (independent of activation), so only the module-defaults option
        // itself was actually missing.
        add_option(OptionKeys::ACTIVE_MODULES, $this->default_active_modules());
    }

    /**
     * The modules a site is seeded with, asked of the modules themselves
     * rather than kept as a second list here — so renaming a module cannot
     * silently break seeding. Consumed as a set; the order is the
     * configured one.
     *
     * @return string[]
     */
    protected function default_active_modules(): array
    {
        return $this->module_catalog()->default_active_slugs();
    }

    /**
     * Shared by seeding and module loading, so one request instantiates each
     * module once and the config file is read once.
     */
    protected function module_catalog(): ModuleCatalog
    {
        if ($this->module_catalog === null) {
            $this->module_catalog = new ModuleCatalog();
        }

        return $this->module_catalog;
    }

    /**
     * Seeds one site's defaults. `flush_rewrite_rules()` is unreliable inside a
     * switched blog, so network paths drop the `rewrite_rules` option instead —
     * that site regenerates them (with the library CPT registered) on its next
     * request.
     */
    protected function seed_site(bool $flush_now = false): void
    {
        add_option(OptionKeys::ACTIVE_MODULES, $this->default_active_modules());

        if ($flush_now) {
            LibraryPostType::register_post_type();
            flush_rewrite_rules(false);
        } else {
            delete_option('rewrite_rules');
        }
    }

    /**
     * A duplicate call (e.g. something re-triggering plugins_loaded, or a
     * defensive re-boot) must attach nothing a second time. $initialized
     * covers every step here; $booted_providers separately covers the
     * provider phase for anything that reaches boot_providers() on its own.
     */
    public function init()
    {
        if ($this->initialized) {
            return;
        }

        if (!$this->check_requirements()) {
            return;
        }

        $this->initialized = true;

        $this->maybe_seed_current_site();
        Route::set_namespace('elemacy');

        do_action(Hooks::REGISTER_PROVIDERS_ACTION, $this);

        $this->register_providers();
        $this->boot_providers();

        $this->load_textdomain();
        $this->handle_version_update();
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

    /**
     * Two independent concerns: which release the site last saw (DB_VERSION —
     * diagnostics/the fired hook's own args only) and which data migrations
     * have actually applied (SCHEMA_VERSION — Migrator's own marker, advanced
     * only inside Migrator::run itself, one completed step at a time).
     */
    public function handle_version_update()
    {
        $is_fresh_install  = get_option(OptionKeys::DB_VERSION, false) === false;
        $installed_version = $is_fresh_install ? ELEMACY_VERSION : get_option(OptionKeys::DB_VERSION);

        if ($installed_version !== ELEMACY_VERSION) {
            update_option(OptionKeys::DB_VERSION, ELEMACY_VERSION);
            do_action(Hooks::VERSION_UPDATED_ACTION, (string) $installed_version, ELEMACY_VERSION);
        }

        // Data migrations do not run here. A migration that reasons about a
        // stored identifier asks the registry that owns it what it means, and
        // no registry is populated yet at `plugins_loaded` — they register on
        // `init`. Migrator owns that scheduling.
        (new Migrator())->schedule($is_fresh_install);
    }

    /**
     * Phase one of every registered provider: bindings and
     * stable feature descriptors only, before any provider's boot() runs —
     * so a provider's boot() can rely on every other enabled provider's own
     * registration already being complete regardless of registration order.
     */
    protected function register_providers(): void
    {
        foreach ($this->providers as $provider) {
            $provider->register(Container::instance());
        }
    }

    /**
     * Phase two: attaches WordPress hooks. This root, not the provider,
     * guarantees each provider boots at most once per request — so a
     * provider (an add-on's included) is correct without defending itself
     * against a second call. Marked before the call, so a provider whose
     * boot() throws is not retried into a half-attached state.
     */
    protected function boot_providers(): void
    {
        foreach ($this->providers as $provider) {
            if ($this->booted_providers->contains($provider)) {
                continue;
            }

            $this->booted_providers->attach($provider);

            $provider->boot();
        }
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
        foreach ($this->module_catalog()->all() as $module) {
            $this->module_manager->register($module);
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
     * items are referenced by ID rather than auto-resolved, so the library
     * always has a default type even with every module off; modules push their
     * own specialized types.
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
