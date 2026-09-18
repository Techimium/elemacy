<?php

namespace Elemacy\Loop\Rendering;

defined('ABSPATH') || exit;

use Elemacy\Elementor\Rendering\TemplateRenderer;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\LoopContext;
use Elemacy\Loop\Rendering\LoopItemStyles;
use Elemacy\Loop\Rendering\LoopTemplateRenderStack;
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

        $identity = new LoopRenderIdentity($placement, (string) $template_id, $source, $item->get_identity(), $occurrence, $batch);

        try {
            return $this->render_entered_item($template_id, $item, $identity);
        } finally {
            TemplateRecursionGuard::exit();
        }
    }

    private function render_entered_item(int $template_id, LoopItemInterface $item, LoopRenderIdentity $identity): bool
    {
        $this->styles->print_base_css($template_id);

        // Whichever item (if any) is already active when this one is
        // entered — captured before push() so a failed enter() below can
        // ask it to re-establish its own native scope, since its partial
        // setup may have disturbed it ("cover partial
        // failure during enter").
        $outer = LoopContext::current();
        $ok = true;

        ob_start();

        LoopContext::push($item);
        LoopRenderContext::push($identity);

        try {
            $entered = false;

            try {
                $item->enter();
                $entered = true;
            } catch (Throwable $e) {
                $ok = false;

                if ($outer) {
                    $outer->enter();
                }
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

            echo '<div class="elemacy-loop-item elemacy-loop-item-' . esc_attr($selector) . '">';
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
