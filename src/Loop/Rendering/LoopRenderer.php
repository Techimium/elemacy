<?php

namespace Elemacy\Loop\Rendering;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\LoopContext;
use Elemacy\Loop\LoopItemStyles;
use Elemacy\Modules\Widgets\Services\LoopTemplateRenderStack;
use Elementor\Plugin;
use Throwable;

/**
 * Owns rendering exactly one loop item's fragment (design.md D8) — the
 * per-item body Grid/Carousel/AJAX each used to duplicate independently.
 * Each caller keeps its own outer layout (wrapper markup, pagination,
 * navigation) and calls render_item() once per item.
 *
 * The pipeline, in order: validate the template exists, acquire a
 * recursion guard, emit base CSS (idempotent per template per request),
 * enter the item's native scope, emit its per-item CSS, render through
 * Elementor, then unwind everything in `finally` — including, on a render
 * failure, discarding this one item's own partially-buffered output rather
 * than leaking a broken fragment into the response. A refused template
 * (missing, or an active recursive cycle/depth violation) or a failed
 * render skips only this one item; it never throws out to the caller, so
 * one bad item can't take down the rest of the response.
 *
 * $placement/$source/$occurrence/$batch build this one occurrence's
 * LoopRenderIdentity (task 8.4/8.7): the DOM class/id and per-item dynamic
 * CSS selector are scoped to it, not to the item's own domain identity
 * alone, so the same post in two widgets — or a post, term and user that
 * happen to share a numeric ID — never collide into one dynamic-CSS scope.
 * $item->get_identity() itself is untouched and keeps reaching dynamic tags
 * unchanged (design.md D4).
 */
final class LoopRenderer
{
    private LoopItemStyles $styles;

    public function __construct(?LoopItemStyles $styles = null)
    {
        $this->styles = $styles ?? new LoopItemStyles();
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
        // setup may have disturbed it (design.md D8's "cover partial
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
                echo Plugin::instance()->frontend->get_builder_content_for_display($template_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's get_builder_content_for_display() returns fully-rendered and escaped HTML
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
        return (bool) Plugin::instance()->documents->get($template_id);
    }
}
