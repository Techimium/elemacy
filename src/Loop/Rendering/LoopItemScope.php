<?php

namespace Elemacy\Loop\Rendering;

defined('ABSPATH') || exit;

use Elemacy\Integrations\Acf\AcfCursor;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Items\WordPressPostScope;
use Elemacy\Loop\LoopContext;
use Throwable;

/**
 * The part of rendering a loop item that does not depend on what the item
 * renders as: pushing its frame, entering and leaving its native scope, the
 * wrapper element, and discarding everything if any of it fails.
 *
 * @internal
 */
final class LoopItemScope
{
    /**
     * @param LoopItemInterface  $item
     * @param LoopRenderIdentity $identity
     * @param string             $source      The data source key.
     * @param int                $template_id The item's Loop Item template; zero when it has none.
     * @param int                $occurrence  Zero-based position within this render.
     * @param callable           $body        Prints the item's content inside the wrapper; returning false means it had nothing to show.
     * @param callable|null      $before      Receives the item's selector, printing anything that belongs before the wrapper.
     * @return bool Whether the item rendered. Prints nothing when it did not.
     */
    public function render(LoopItemInterface $item, LoopRenderIdentity $identity, string $source, int $template_id, int $occurrence, callable $body, ?callable $before = null): bool
    {
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
                $ok = $this->render_markup($item, $identity, $body, $before);
            }
        } finally {
            LoopRenderContext::pop();
            LoopContext::pop();
        }

        $fragment = ob_get_clean();

        if ($ok) {
            echo $fragment; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- buffered output from this same method's own echo calls and the caller's own already escaped body.
        }

        return $ok;
    }

    private function render_markup(LoopItemInterface $item, LoopRenderIdentity $identity, callable $body, ?callable $before): bool
    {
        try {
            $selector = $identity->selector();

            if (null !== $before) {
                $before($selector);
            }

            // data-elemacy-item is the item's own identity, so Load More and
            // Infinite Scroll can skip an item already on screen.
            echo '<div class="elemacy-loop-item elemacy-loop-item-' . esc_attr($selector) . '" data-elemacy-item="' . esc_attr($item->get_identity()) . '">';
            if (false === $body()) {
                return false;
            }

            echo '</div>';

            return true;
        } catch (Throwable $e) {
            return false;
        } finally {
            $item->exit();
        }
    }
}
