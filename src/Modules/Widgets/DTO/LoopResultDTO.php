<?php

namespace Elemacy\Modules\Widgets\DTO;

defined('ABSPATH') || exit;

use Elemacy\Core\DTO\DTO;
use Elemacy\Modules\Widgets\Contracts\LoopItemInterface;

/**
 * One data source's resolved items for one widget render, plus enough
 * pagination bookkeeping for the loop widgets' existing pagination UI to
 * keep working regardless of which source produced the items.
 */
class LoopResultDTO extends DTO
{
    /** @var LoopItemInterface[] */
    public $items = [];

    /** @var int Total matching items across every page. */
    public $total_items = 0;

    /** @var int Number of pages at the current per-page size. */
    public $max_num_pages = 0;

    /** @var bool Whether this source's items can be paginated at all. */
    public $supports_pagination = false;

    /**
     * @param LoopItemInterface[] $items
     */
    public function __construct(array $items = [], int $total_items = 0, int $max_num_pages = 0, bool $supports_pagination = false)
    {
        $this->items = $items;
        $this->total_items = $total_items;
        $this->max_num_pages = $max_num_pages;
        $this->supports_pagination = $supports_pagination;
    }
}
