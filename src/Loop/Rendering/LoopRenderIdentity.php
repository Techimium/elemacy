<?php

namespace Elemacy\Loop\Rendering;

defined('ABSPATH') || exit;

use Elemacy\Loop\Support\LoopItemIdentity;

/**
 * The full presentation identity of one rendered loop item — everything names
 * as distinguishing one occurrence from another: placement/widget instance,
 * template, source namespace, item identity, occurrence (this item repeating
 * within one render), and AJAX batch context. See docs/ARCHITECTURE.md §
 * Rationale digest.
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
