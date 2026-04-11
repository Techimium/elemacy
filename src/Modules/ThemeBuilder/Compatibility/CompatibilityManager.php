<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\AstraCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\BBThemeCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\BlocksyCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\DefaultCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\GlobalCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\GenesisCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\GeneratePressCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\KadenceCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\NeveCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\OceanWPCompatibility;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\StorefrontCompatibility;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

class CompatibilityManager
{
    /**
     * @var CompatibilityManager|null
     */
    protected static $instance = null;

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

    public function boot(): void
    {
        $manager = ThemeBuilderManager::instance();

        $has_header = (bool) $manager->get_header_id();
        $has_footer = (bool) $manager->get_footer_id();

        if (!$has_header && !$has_footer) {
            return;
        }

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

    protected function resolve_compatibility(array $theme_slugs)
    {
        $map = [
            'astra' => AstraCompatibility::class,
            'generatepress' => GeneratePressCompatibility::class,
            'oceanwp' => OceanWPCompatibility::class,
            'kadence' => KadenceCompatibility::class,
            'neve' => NeveCompatibility::class,
            'blocksy' => BlocksyCompatibility::class,
            'storefront' => StorefrontCompatibility::class,
            'genesis' => GenesisCompatibility::class,
            'bb-theme' => BBThemeCompatibility::class,
            'hello-elementor' => DefaultCompatibility::class,
        ];

        foreach ($theme_slugs as $slug) {
            if (isset($map[$slug]) && class_exists($map[$slug])) {
                return new $map[$slug]();
            }
        }

        // Global fallback to support unknown themes without swapping templates.
        return class_exists(GlobalCompatibility::class) ? new GlobalCompatibility() : (class_exists(DefaultCompatibility::class) ? new DefaultCompatibility() : null);
    }
}

