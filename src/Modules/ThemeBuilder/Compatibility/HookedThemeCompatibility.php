<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

/**
 * Base for a theme whose header and footer print on its own action hooks. An
 * adapter declares, per side, where the Elemacy template renders and how the
 * theme's own chrome is taken away; this class does both, for each side a
 * template replaces.
 *
 * Chrome is declared as data rather than as hand-picked function names
 * wherever possible: on a hook that carries nothing but chrome, every
 * callback the theme itself attached is removed (ThemeHookCleaner), so a
 * theme renaming its markup function, or adding a header builder, needs no
 * change here. Only a hook that mixes chrome with page content names the
 * chrome callbacks one by one; a name the theme no longer registers is
 * recorded in missing_callbacks() so the pinned-theme tests catch the drift.
 */
abstract class HookedThemeCompatibility implements ThemeCompatibilityInterface
{
    /** @var string[] "hook: callback" entries declared but not registered by the theme. */
    private array $missing = [];

    /**
     * Per side ('header', 'footer'), the theme hook the Elemacy template
     * renders on and the priority it renders at.
     *
     * @return array<string, array{hook: string, priority?: int}>
     */
    abstract protected function chrome_locations(): array;

    /**
     * Per side, theme hooks whose theme-attached callbacks are all part of
     * that side's chrome (its markup, a top bar, a mobile header), mapped to
     * the highest priority to clean on them.
     *
     * @return array<string, array<string, int>>
     */
    protected function chrome_hooks(): array
    {
        return [];
    }

    /**
     * Per side, chrome callbacks on hooks that also carry page content, as
     * [hook, callback, priority].
     *
     * @return array<string, array<int, array{0: string, 1: callable-string, 2: int}>>
     */
    protected function chrome_callbacks(): array
    {
        return [];
    }

    /**
     * Per side, theme filters that switch the theme's own chrome off, the
     * theme's public way to do so, applied before anything renders.
     *
     * @return array<string, string[]>
     */
    protected function chrome_switches(): array
    {
        return [];
    }

    /**
     * Sides whose location the theme itself wraps in its own `<header>` or
     * `<footer>` element, so the template must not add a second landmark of
     * the same kind.
     *
     * @return string[]
     */
    protected function theme_landmark_sides(): array
    {
        return [];
    }

    public function register(ThemeBuilderManager $manager): void
    {
        foreach (['header', 'footer'] as $side) {
            $location = $this->chrome_locations()[$side] ?? null;

            if (null === $location || !$manager->replaces_native_chrome($side)) {
                continue;
            }

            if (in_array($side, $this->theme_landmark_sides(), true)) {
                $manager->keep_theme_landmark($side);
            }

            foreach ($this->chrome_switches()[$side] ?? [] as $filter) {
                add_filter($filter, '__return_false');
            }

            add_action('template_redirect', function () use ($side): void {
                $this->remove_native_chrome($side);
            }, 10);

            add_action($location['hook'], [$manager, 'render_' . $side], $location['priority'] ?? 10);
        }
    }

    /**
     * "hook: callback" for each declared chrome callback the active theme did
     * not register on this request, so a theme update that renamed one is
     * visible instead of leaving its chrome on the page.
     *
     * @return string[]
     */
    public function missing_callbacks(): array
    {
        return $this->missing;
    }

    private function remove_native_chrome(string $side): void
    {
        foreach ($this->chrome_hooks()[$side] ?? [] as $hook => $max_priority) {
            ThemeHookCleaner::remove_theme_callbacks($hook, $max_priority);
        }

        foreach ($this->chrome_callbacks()[$side] ?? [] as [$hook, $callback, $priority]) {
            if (!remove_action($hook, $callback, $priority)) {
                $this->missing[] = $hook . ': ' . $callback;
            }
        }
    }
}
