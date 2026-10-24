<?php

namespace Elemacy\Loop;

defined('ABSPATH') || exit;

/**
 * Everything one loop query is asked with: the settings to run, the loop frame
 * it renders inside (if any), the page, and whether it is an editor preview.
 * New query abilities arrive as new fields here, not as new interfaces.
 */
final class LoopQuery
{
    private array $settings;
    private ?LoopFrame $parent;
    private int $page;
    private bool $is_preview;

    public function __construct(array $settings, ?LoopFrame $parent = null, int $page = 1, bool $is_preview = false)
    {
        $this->settings = $settings;
        $this->parent = $parent;
        $this->page = max(1, $page);
        $this->is_preview = $is_preview;
    }

    /** The editor's widget settings, or what the source's public policy returned for a visitor. */
    public function settings(): array
    {
        return $this->settings;
    }

    /** The loop frame this loop renders inside, or null at the top level. */
    public function parent(): ?LoopFrame
    {
        return $this->parent;
    }

    public function page(): int
    {
        return $this->page;
    }

    public function is_preview(): bool
    {
        return $this->is_preview;
    }
}
