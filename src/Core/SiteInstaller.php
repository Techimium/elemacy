<?php

namespace Elemacy\Core;

if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\Constants\OptionKeys;
use Elemacy\Core\Multisite\SiteBatchIterator;
use Elemacy\TemplateLibrary\LibraryPostType;

/**
 * A site's install state: seeding its defaults (on activation, for a site
 * created under network activation, in bounded network batches, and as a
 * per-request safety net) and recording which release it last saw.
 *
 * Several of these paths run where the plugin's request bootstrap never does
 * — the activation request, and plugins_loaded@1 before any provider has
 * registered — so this class and its dependencies must stay buildable by the
 * container's auto-wiring alone. Never bind it, or anything it takes, in a
 * provider: the binding would not exist on those paths.
 */
class SiteInstaller
{
    /**
     * The site-count-independent option a bounded network activation leaves
     * behind when more sites remain than fit in one batch.
     */
    public const NETWORK_SEED_OFFSET_OPTION = 'elemacy_network_seed_offset';

    public const NETWORK_SEED_BATCH_SIZE = 100;

    protected ModuleCatalog $catalog;
    protected SiteBatchIterator $sites;
    protected Migrator $migrator;

    public function __construct(ModuleCatalog $catalog, SiteBatchIterator $sites, Migrator $migrator)
    {
        $this->catalog = $catalog;
        $this->sites = $sites;
        $this->migrator = $migrator;
    }

    /**
     * Must run while the plugin file loads: register_activation_hook()
     * attaches nothing once that moment has passed.
     */
    public function register_hooks(): void
    {
        register_activation_hook(ELEMACY_FILE, [$this, 'activate']);

        // Sites created after a network activation need the same seeding.
        add_action('wp_initialize_site', [$this, 'seed_new_site'], 10, 1);

        // Resumes a bounded network seed a prior activation request did not
        // finish, a small amount of work per request until exhausted —
        // never a second unbounded pass.
        add_action('plugins_loaded', [$this, 'resume_network_seeding'], 1);
    }

    /**
     * @param bool $network_wide
     */
    public function activate($network_wide = false): void
    {
        if (is_multisite() && $network_wide) {
            update_site_option(self::NETWORK_SEED_OFFSET_OPTION, 0);
            $this->continue_network_seeding();

            return;
        }

        $this->seed_site(true);
    }

    /**
     * @param \WP_Site|object $new_site
     */
    public function seed_new_site($new_site): void
    {
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
    }

    public function resume_network_seeding(): void
    {
        if (!is_multisite() || get_site_option(self::NETWORK_SEED_OFFSET_OPTION, false) === false) {
            return;
        }

        $this->continue_network_seeding();
    }

    /**
     * The safety net under both the bounded batch above and
     * wp_initialize_site: a site that is somehow still unseeded when it
     * receives its own real request (the batch has not reached it yet, or
     * predates this mechanism entirely) seeds itself here, once, on that
     * request — no coordination with the network-wide job required.
     */
    public function seed_current_site_if_missing(): void
    {
        if (get_option(OptionKeys::ACTIVE_MODULES, null) !== null) {
            return;
        }

        // Unlike seed_site(), no rewrite-rules flush: by the time a site
        // reaches its own normal request, the library CPT is already
        // registered through this plugin's ordinary per-request bootstrap
        // (independent of activation), so only the module-defaults option
        // itself was actually missing.
        add_option(OptionKeys::ACTIVE_MODULES, $this->catalog->default_active_slugs());
    }

    /**
     * Two independent concerns: which release the site last saw (DB_VERSION —
     * diagnostics/the fired hook's own args only) and which data migrations
     * have actually applied (SCHEMA_VERSION — Migrator's own marker, advanced
     * only inside Migrator::run itself, one completed step at a time).
     */
    public function sync_version(): void
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
        $this->migrator->schedule($is_fresh_install);
    }

    protected function continue_network_seeding(): void
    {
        $offset = (int) get_site_option(self::NETWORK_SEED_OFFSET_OPTION, 0);

        $result = $this->sites->process_batch(
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
     * Seeds one site's defaults — exactly the modules that declare themselves
     * default-active, asked of the modules rather than kept as a second list
     * here. `flush_rewrite_rules()` is unreliable inside a switched blog, so
     * network paths drop the `rewrite_rules` option instead — that site
     * regenerates them (with the library CPT registered) on its next request.
     */
    protected function seed_site(bool $flush_now = false): void
    {
        add_option(OptionKeys::ACTIVE_MODULES, $this->catalog->default_active_slugs());

        if ($flush_now) {
            LibraryPostType::register_post_type();
            flush_rewrite_rules(false);
        } else {
            delete_option('rewrite_rules');
        }
    }
}
