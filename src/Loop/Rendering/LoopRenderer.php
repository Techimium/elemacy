<?php

namespace Elemacy\Loop\Rendering;

defined('ABSPATH') || exit;

use Elemacy\Elementor\Rendering\TemplateRenderer;
use Elemacy\Integrations\Acf\AcfCursor;
use Elemacy\Loop\Items\WordPressPostScope;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\LoopContext;
use Throwable;

/**
 * Owns rendering exactly one loop item's fragment — the per-item body
 * Grid/Carousel/AJAX each used to duplicate independently. Each caller keeps
 * its own outer layout (wrapper markup, pagination, navigation) and calls
 * render_item() once per item. See docs/ARCHITECTURE.md § Shared domains.
 */
final class LoopRenderer
{
    private LoopItemStyles $styles;
    private TemplateRenderer $templates;

    public function __construct(?LoopItemStyles $styles = null, ?TemplateRenderer $templates = null)
    {
        $this->styles = $styles ?? new LoopItemStyles();
        $this->templates = $templates ?? new TemplateRenderer();
    }

    public function render_item(
        int $template_id,
        LoopItemInterface $item,
        string $placement,
        string $source,
        int $occurrence = 0,
        string $batch = ''
    ): bool {
        if (!$this->template_exists($template_id)) {
            return false;
        }

        try {
            TemplateRecursionGuard::enter($template_id);
        } catch (TemplateRecursionException $e) {
            return false;
        }

        $parent = LoopContext::current_frame();
        $identity = new LoopRenderIdentity(
            $placement,
            (string) $template_id,
            $source,
            $item->get_identity(),
            $occurrence,
            $batch,
            $parent ? $parent->render_key() : ''
        );

        try {
            return $this->render_entered_item($template_id, $item, $identity, $source, $occurrence);
        } finally {
            TemplateRecursionGuard::exit();
        }
    }

    private function render_entered_item(int $template_id, LoopItemInterface $item, LoopRenderIdentity $identity, string $source, int $occurrence): bool
    {
        $this->styles->print_base_css($template_id);

        $ok = false;

        ob_start();

        // The frame is pushed before enter(), and carries the item's address
        // (the enclosing frame's address plus its own step), so everything
        // inside — a nested loop, a dynamic tag, a later-page token — sees the
        // full chain.
        LoopContext::push($item, $source, $template_id, $occurrence, $identity->key());
        LoopRenderContext::push($identity);

        try {
            $entered = false;

            // An item whose enter() throws is skipped. Whatever native state
            // it changed before failing is put back from a snapshot; the
            // enclosing item is never re-entered, since that would replace its
            // native scope (an ACF row's cursor snapshot) instead of restoring it.
            $posts = WordPressPostScope::snapshot();
            $acf_rows = AcfCursor::snapshot();

            try {
                $item->enter();
                $entered = true;
            } catch (Throwable $e) {
                $entered = false;
                WordPressPostScope::restore_snapshot($posts);
                AcfCursor::restore($acf_rows);
            }

            if ($entered) {
                $ok = $this->render_item_markup($template_id, $item, $identity);
            }
        } finally {
            LoopRenderContext::pop();
            LoopContext::pop();
        }

        $fragment = ob_get_clean();

        if ($ok) {
            echo $fragment; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- buffered output from this same method's own echo calls below, already Elementor-rendered/escaped content.
        }

        return $ok;
    }

    private function render_item_markup(int $template_id, LoopItemInterface $item, LoopRenderIdentity $identity): bool
    {
        try {
            $selector = $identity->selector();
            $this->styles->print_item_css($template_id, $item->get_identity(), $selector);

            // data-elemacy-item is the item's own identity, so Load More and
            // Infinite Scroll can skip an item already on screen.
            echo '<div class="elemacy-loop-item elemacy-loop-item-' . esc_attr($selector) . '" data-elemacy-item="' . esc_attr($item->get_identity()) . '">';
            LoopTemplateRenderStack::push($template_id);
            try {
                echo $this->templates->render($template_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- TemplateRenderer returns Elementor's fully rendered and escaped HTML.
            } finally {
                LoopTemplateRenderStack::pop();
            }
            echo '</div>';

            return true;
        } catch (Throwable $e) {
            return false;
        } finally {
            $item->exit();
        }
    }

    private function template_exists(int $template_id): bool
    {
        return $this->templates->exists($template_id);
    }
}
