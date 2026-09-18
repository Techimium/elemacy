<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\AstraCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\BBThemeCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\BlocksyCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\BlockThemeCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\DefaultCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\GlobalCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\GenesisCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\GeneratePressCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\KadenceCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\NeveCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\OceanWPCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\StorefrontCompatibility;
use Elemacy\Modules\ThemeBuilder\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

/** Selects and boots exactly one theme compatibility adapter per request. */
class CompatibilityManager
{
    /**
     * @var CompatibilityManager|null
     */
    protected static $instance = null;

    /** @var array<string, class-string<ThemeCompatibilityInterface>> */
    protected array $adapters = [];

    public static function instance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new self();
        }

        return static::$instance;
    }

    public function register_hooks(): void
    {
        add_action('wp', [$this, 'boot']);
    }

    /**
     * Registers a theme compatibility adapter for an exact theme slug
     * (stylesheet or template directory name). A later registration for the
     * same slug replaces the earlier one.
     *
     * @param string                                    $theme_slug
     * @param class-string<ThemeCompatibilityInterface> $adapter_class
     * @return void
     */
    public function register(string $theme_slug, string $adapter_class): void
    {
        $this->adapters[$theme_slug] = $adapter_class;
    }

    public function boot(): void
    {
        $manager = ThemeBuilderManager::instance();

        $has_header = (bool) $manager->get_header_id();
        $has_footer = (bool) $manager->get_footer_id();

        if (!$has_header && !$has_footer) {
            return;
        }

        $this->register_default_adapters();

        $theme = wp_get_theme();
        $theme_slugs = array_values(array_unique(array_filter([
            $theme->get_stylesheet(),
            $theme->get_template(),
        ])));

        $compat = $this->resolve_compatibility($theme_slugs);

        if (!$compat) {
            return;
        }

        $compat->register($manager);
    }

    /**
     * Registers the adapters this module ships, then fires the extension point
     * for add-ons/site-specific adapters.
     *
     * @return void
     */
    protected function register_default_adapters(): void
    {
        $this->register('astra', AstraCompatibility::class);
        $this->register('generatepress', GeneratePressCompatibility::class);
        $this->register('oceanwp', OceanWPCompatibility::class);
        $this->register('kadence', KadenceCompatibility::class);
        $this->register('neve', NeveCompatibility::class);
        $this->register('blocksy', BlocksyCompatibility::class);
        $this->register('storefront', StorefrontCompatibility::class);
        $this->register('genesis', GenesisCompatibility::class);
        $this->register('bb-theme', BBThemeCompatibility::class);
        $this->register('hello-elementor', DefaultCompatibility::class);

        do_action(Hooks::THEME_BUILDER_COMPATIBILITY_REGISTER_ACTION, $this);
    }

    protected function resolve_compatibility(array $theme_slugs): ?ThemeCompatibilityInterface
    {
        // Block themes share one template-part-based mechanism regardless
        // of slug; a dedicated per-theme adapter (if one is ever needed)
        // can still be registered and will be preferred below instead.
        foreach ($theme_slugs as $slug) {
            if (isset($this->adapters[$slug]) && class_exists($this->adapters[$slug])) {
                return new $this->adapters[$slug]();
            }
        }

        if (wp_is_block_theme() && class_exists(BlockThemeCompatibility::class)) {
            return new BlockThemeCompatibility();
        }

        // Global fallback to support unknown classic themes without swapping templates.
        return class_exists(GlobalCompatibility::class) ? new GlobalCompatibility() : (class_exists(DefaultCompatibility::class) ? new DefaultCompatibility() : null);
    }
}
