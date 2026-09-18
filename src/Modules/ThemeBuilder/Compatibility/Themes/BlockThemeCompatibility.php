<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

/**
 * Block theme compatibility.
 *
 * A block theme has no classic header.php/footer.php — get_header()/
 * get_footer() never fire, so no other adapter's hooks apply. It renders
 * its header/footer as core/template-part blocks instead ("header"/"footer"
 * slugs, the convention WordPress's own bundled themes and most third-party
 * block themes use). Replacing that block's own rendered content — the
 * documented, native override point for a template part, not a removed
 * hook — swaps in Elemacy's content without touching the theme's block
 * template at all.
 */
class BlockThemeCompatibility implements ThemeCompatibilityInterface
{
    public function register(ThemeBuilderManager $manager): void
    {
        if (!$manager->get_header_id() && !$manager->get_footer_id()) {
            return;
        }

        add_filter('render_block_core/template-part', function ($content, $block) use ($manager) {
            $slug = $block['attrs']['slug'] ?? null;

            if ('header' === $slug && $manager->get_header_id()) {
                return $this->buffer([$manager, 'render_header']);
            }
            if ('footer' === $slug && $manager->get_footer_id()) {
                return $this->buffer([$manager, 'render_footer']);
            }

            return $content;
        }, 10, 2);
    }

    private function buffer(callable $render): string
    {
        ob_start();
        $render();

        return ob_get_clean();
    }
}
