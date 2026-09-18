<?php

namespace Elemacy\Loop\Rendering;

defined('ABSPATH') || exit;

use Elemacy\Loop\Support\LoopItemIdentity;

/**
 * The full presentation identity of one rendered loop item — everything
 * design.md D8 names as distinguishing one occurrence from another:
 * placement/widget instance, template, source namespace, item identity,
 * occurrence (this item repeating within one render), and AJAX batch
 * context. Used for per-item selectors and DOM IDs (dynamic CSS scoping,
 * task 8.7); never for the item's own domain identity, which
 * LoopItemInterface::get_identity() continues to own unchanged for tags and
 * data (design.md D4).
 *
 * The one property this exists to guarantee: the same numeric domain ID
 * from two different placements, sources, or templates must never collapse
 * into the same render scope, even though LoopItemIdentity::sanitize() alone
 * would happily produce the same string for both.
 */
final class LoopRenderIdentity
{
    private string $placement;
    private string $template;
    private string $source;
    private string $item;
    private int $occurrence;
    private string $batch;

    public function __construct(string $placement, string $template, string $source, string $item, int $occurrence = 0, string $batch = '')
    {
        $this->placement = $placement;
        $this->template = $template;
        $this->source = $source;
        $this->item = $item;
        $this->occurrence = $occurrence;
        $this->batch = $batch;
    }

    /**
     * A key unique to exactly this rendered occurrence. Keeps the item's own
     * sanitized identity readable up front (matching existing markup, e.g.
     * `elemacy-loop-item-{identity}`) and disambiguates every other named
     * component behind a short hash suffix, rather than concatenating raw
     * parts — which would either collide (a joining character that can also
     * appear inside a sanitized part) or need every part escaped for CSS/DOM
     * use. Not designed to be parsed back into its parts.
     */
    public function key(): string
    {
        $scope = substr(md5(implode('|', [
            $this->placement,
            $this->template,
            $this->source,
            (string) $this->occurrence,
            $this->batch,
        ])), 0, 12);

        return LoopItemIdentity::sanitize($this->item) . '-' . $scope;
    }

    /** Safe to use directly as a CSS class token or HTML id attribute. */
    public function selector(): string
    {
        return $this->key();
    }
}
