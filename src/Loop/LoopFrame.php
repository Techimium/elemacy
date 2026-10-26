<?php

namespace Elemacy\Loop;

defined('ABSPATH') || exit;

use Elemacy\Loop\Address\ItemAddress;
use Elemacy\Loop\Contracts\LoopItemInterface;

/**
 * One loop item as it is being rendered: the item, where it lives (its
 * address), which source and template rendered it, its position in that render
 * and the render key that scopes its styles.
 */
final class LoopFrame
{
    private LoopItemInterface $item;
    private ?ItemAddress $address;
    private string $source_key;
    private int $template_id;
    private int $index;
    private string $render_key;

    public function __construct(LoopItemInterface $item, ?ItemAddress $address, string $source_key = '', int $template_id = 0, int $index = 0, string $render_key = '')
    {
        $this->item = $item;
        $this->address = $address;
        $this->source_key = $source_key;
        $this->template_id = $template_id;
        $this->index = $index;
        $this->render_key = $render_key;
    }

    public function item(): LoopItemInterface
    {
        return $this->item;
    }

    /** Null when the frame sits deeper than the nesting limit allows an address. */
    public function address(): ?ItemAddress
    {
        return $this->address;
    }

    public function source_key(): string
    {
        return $this->source_key;
    }

    public function template_id(): int
    {
        return $this->template_id;
    }

    public function index(): int
    {
        return $this->index;
    }

    public function render_key(): string
    {
        return $this->render_key;
    }
}
