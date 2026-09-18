<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

/** Block theme compatibility. */
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
