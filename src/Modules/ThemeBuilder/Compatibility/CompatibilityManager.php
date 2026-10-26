<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\AstraCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\BBThemeCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\BlocksyCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\BlockThemeCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\HelloElementorCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\ClassicThemeCompatibility;
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

    /**
     * The adapter serving this request, once booted.
     *
     * @var ThemeCompatibilityInterface|null
     */
    protected ?ThemeCompatibilityInterface $active = null;

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
     * @param string $theme_slug
     * @param string $adapter_class A ThemeCompatibilityInterface class name.
     * @return void
     */
    public function register(string $theme_slug, string $adapter_class): void
    {
        $this->adapters[$theme_slug] = $adapter_class;
    }

    public function boot(): void
    {
        $this->active = null;
        $manager = ThemeBuilderManager::instance();

        $has_header = $manager->replaces_native_chrome('header');
        $has_footer = $manager->replaces_native_chrome('footer');

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

        $this->active = $compat;
        $compat->register($manager);

        // A block theme renders its chrome as template parts, but a classic
        // PHP template served on it — Elementor's Full Width layout calls
        // get_header()/get_footer() — would load WordPress's deprecated
        // theme-compat header and footer instead. The generic adapter
        // replaces those as it does for any theme without header.php; the
        // render-once guard keeps each side single whichever path prints it.
        if ($compat instanceof BlockThemeCompatibility && class_exists(ClassicThemeCompatibility::class)) {
            (new ClassicThemeCompatibility())->register($manager);
        }
    }

    /** The adapter replacing native chrome on this request, or null when none does. */
    public function active(): ?ThemeCompatibilityInterface
    {
        return $this->active;
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
        $this->register('hello-elementor', HelloElementorCompatibility::class);

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
        return class_exists(ClassicThemeCompatibility::class) ? new ClassicThemeCompatibility() : (class_exists(HelloElementorCompatibility::class) ? new HelloElementorCompatibility() : null);
    }
}
