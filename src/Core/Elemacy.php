<?php

namespace Elemacy\Core;

if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\Contracts\ServiceProviderInterface;
use Elemacy\Core\Providers\CoreServiceProvider;
use Elemacy\Core\Hooks;
use Elemacy\Http\Route;

/**
 * The composition root: connects the plugin's parts in order and holds no
 * feature logic itself. Site setup lives in SiteInstaller, module loading in
 * ModuleLoader; everything Core attaches to WordPress lives in
 * CoreServiceProvider's bootstraps.
 */
class Elemacy
{
    protected static $instance = null;

    /** @var ServiceProviderInterface[] */
    protected array $providers = [];

    /**
     * Guards the whole bootstrap: every step in init — provider
     * registration/boot, module loading — assumes it runs exactly once per
     * request. It is also what boots each provider at most once, so a
     * provider never has to defend itself against a second boot.
     *
     * @var bool
     */
    protected bool $initialized = false;

    protected function __construct()
    {
        // Resolved now, while the plugin file loads, so the activation hook
        // can still attach. Auto-wired: no provider has registered yet.
        $this->site_installer()->register_hooks();

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

    public function init()
    {
        if ($this->initialized) {
            return;
        }

        if (!$this->check_requirements()) {
            return;
        }

        $this->initialized = true;

        $this->site_installer()->seed_current_site_if_missing();
        Route::set_namespace('elemacy');

        do_action(Hooks::REGISTER_PROVIDERS_ACTION, $this);

        $this->register_providers();
        $this->boot_providers();

        $this->load_textdomain();
        $this->site_installer()->sync_version();
        Container::instance()->make(ModuleLoader::class)->load();

        do_action(Hooks::LOADED_ACTION);
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('elemacy', false, dirname(ELEMACY_PLUGIN_BASE) . '/languages');
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
     * Phase two: attaches WordPress hooks. Runs once per request because
     * init() does.
     */
    protected function boot_providers(): void
    {
        foreach ($this->providers as $provider) {
            $provider->boot();
        }
    }

    /**
     * The container-shared manager ModuleLoader loads into. Safe to call
     * before init() or after a failed requirements check: it then returns a
     * manager that simply has no modules.
     */
    public function get_module_manager(): ModuleManager
    {
        return Container::instance()->singleton(ModuleManager::class);
    }

    protected function site_installer(): SiteInstaller
    {
        return Container::instance()->singleton(SiteInstaller::class);
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
