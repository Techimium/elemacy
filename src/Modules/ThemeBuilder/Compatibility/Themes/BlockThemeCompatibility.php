<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

/**
 * Block theme compatibility. A template part is the theme's header or footer
 * when its area is header or footer, whatever its slug. A part whose area is
 * not declared (an older theme that lists no template parts) falls back to
 * the conventional slugs `header` and `footer`.
 */
class BlockThemeCompatibility implements ThemeCompatibilityInterface
{
    /** @var array<string, string|null> Side of each part looked up this request, by "theme//slug". */
    private array $sides = [];

    public function register(ThemeBuilderManager $manager): void
    {
        if (!$manager->replaces_native_chrome('header') && !$manager->replaces_native_chrome('footer')) {
            return;
        }

        add_filter('render_block_core/template-part', function ($content, $block) use ($manager) {
            $side = $this->side_of((array) ($block['attrs'] ?? []));

            if ('header' === $side && $manager->replaces_native_chrome('header')) {
                return $this->buffer([$manager, 'render_header']);
            }
            if ('footer' === $side && $manager->replaces_native_chrome('footer')) {
                return $this->buffer([$manager, 'render_footer']);
            }

            return $content;
        }, 10, 2);
    }

    /**
     * @param array<string, mixed> $attrs The template-part block's attributes.
     * @return string|null 'header', 'footer', or null for any other part.
     */
    private function side_of(array $attrs): ?string
    {
        $slug = isset($attrs['slug']) && is_string($attrs['slug']) ? $attrs['slug'] : '';
        $area = isset($attrs['area']) && is_string($attrs['area']) ? $attrs['area'] : '';

        if ('' === $area && '' !== $slug) {
            $theme = isset($attrs['theme']) && is_string($attrs['theme']) ? $attrs['theme'] : get_stylesheet();
            $key = "$theme//$slug";
            if (!array_key_exists($key, $this->sides)) {
                $part = get_block_template($key, 'wp_template_part');
                $this->sides[$key] = $part && is_string($part->area ?? null) ? $part->area : null;
            }
            $area = (string) $this->sides[$key];
        }

        if ('header' === $area || 'footer' === $area) {
            return $area;
        }

        return in_array($slug, ['header', 'footer'], true) ? $slug : null;
    }

    private function buffer(callable $render): string
    {
        ob_start();
        $render();

        return ob_get_clean();
    }
}
