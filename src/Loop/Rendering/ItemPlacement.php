<?php

namespace Elemacy\Loop\Rendering;

defined('ABSPATH') || exit;

/**
 * Where one item sits in the current render: which widget instance, source and
 * position, and which page of the list it belongs to.
 *
 * @internal
 */
final class ItemPlacement
{
    private string $placement;
    private string $source;
    private int $occurrence;
    private string $batch;
    private int $page;

    /**
     * @param string $placement  The rendering widget instance.
     * @param string $source     The data source key.
     * @param int    $occurrence Zero-based position within this render.
     * @param string $batch      Distinguishes a page loaded in place; empty on the first render.
     * @param int    $page       One-based page of the list this item belongs to.
     */
    public function __construct(string $placement, string $source, int $occurrence = 0, string $batch = '', int $page = 1)
    {
        $this->placement = $placement;
        $this->source = $source;
        $this->occurrence = $occurrence;
        $this->batch = $batch;
        $this->page = $page;
    }

    public function placement(): string
    {
        return $this->placement;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function occurrence(): int
    {
        return $this->occurrence;
    }

    public function batch(): string
    {
        return $this->batch;
    }

    public function page(): int
    {
        return $this->page;
    }
}
