<?php

namespace Elemacy\Core\DTO;

defined('ABSPATH') || exit;

/**
 * A page of results plus its pagination numbers. Services return this from their
 * list queries; the controller serializes `items` through its Resource and emits
 * `pagination()` alongside, so the paged envelope shape is defined in one place.
 */
class PaginatedResultDTO extends DTO
{
    /** @var array<int, mixed> The item DTOs for this page (serialized by the controller's Resource). */
    public $items = [];

    /** @var int Total matching items across every page. */
    public $total = 0;

    /** @var int Number of pages at the current per-page size. */
    public $total_pages = 0;

    /** @var int The 1-based page this result represents. */
    public $page = 1;

    /** @var int Items per page used to build this result. */
    public $per_page = 20;

    /**
     * Builds a page result from its items and totals.
     *
     * @param array<int, mixed> $items
     */
    public function __construct(array $items = [], int $total = 0, int $total_pages = 0, int $page = 1, int $per_page = 20)
    {
        $this->items = $items;
        $this->total = $total;
        $this->total_pages = $total_pages;
        $this->page = $page;
        $this->per_page = $per_page;
    }

    /**
     * The pagination numbers as sent to the client, nested under the response `data`.
     *
     * @return array{total: int, total_pages: int, page: int, per_page: int}
     */
    public function pagination(): array
    {
        return [
            'total' => $this->total,
            'total_pages' => $this->total_pages,
            'page' => $this->page,
            'per_page' => $this->per_page,
        ];
    }
}
