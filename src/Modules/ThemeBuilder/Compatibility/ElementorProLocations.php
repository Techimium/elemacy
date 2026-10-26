<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility;

defined('ABSPATH') || exit;

/**
 * Which Theme Builder locations Elementor Pro's own Theme Builder serves on
 * this request, so Elemacy leaves those to it rather than printing a second
 * document shell or a second header. Asked through Elementor Pro's public
 * `elementor_location_exits()`, the function themes use for the same purpose.
 */
final class ElementorProLocations
{
    /**
     * Elemacy template type => Elementor Pro location. Pro serves 404 pages
     * from its `single` location and search results from `archive`.
     */
    private const LOCATIONS = [
        'header'  => 'header',
        'footer'  => 'footer',
        'single'  => 'single',
        '404'     => 'single',
        'archive' => 'archive',
        'search'  => 'archive',
    ];

    /** @var array<string, bool> Pro location => whether Pro serves it on this request. */
    private array $served = [];

    /** Whether Elementor Pro's Theme Builder is loaded at all. */
    public static function is_active(): bool
    {
        return function_exists('elementor_location_exits');
    }

    /**
     * Whether Elementor Pro has a template matching this request for the
     * location Elemacy calls $type. Never true before the main query has
     * run, since Pro's conditions read it.
     */
    public function serves(string $type): bool
    {
        $location = self::LOCATIONS[$type] ?? null;

        if (null === $location || !self::is_active() || !did_action('wp')) {
            return false;
        }

        if (!array_key_exists($location, $this->served)) {
            $this->served[$location] = (bool) elementor_location_exits($location, true);
        }

        return $this->served[$location];
    }

    /** Lets the next request ask Elementor Pro again. */
    public function forget(): void
    {
        $this->served = [];
    }
}
