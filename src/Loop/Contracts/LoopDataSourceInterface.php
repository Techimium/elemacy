<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

use Elemacy\Elementor\Documents\PreviewablePageBase;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Elementor\Widgets\BaseWidget;

/** Supplies the items a Loop Grid / Loop Carousel widget renders. */
interface LoopDataSourceInterface
{
    /**
     * Stable identifier stored in the widget's `data_source` setting.
     *
     * @return string
     */
    public function get_key(): string;

    /**
     * Label shown for this source in the Data Source control.
     *
     * @return string
     */
    public function get_label(): string;

    /**
     * Adds this source's own Query section controls to the widget, each
     * scoped with `condition => ['data_source' => $this->get_key]` (in
     * addition to whatever conditions the control itself needs) so it is
     * only visible when this source is selected.
     *
     * @param BaseWidget $widget
     * @return void
     */
    public function register_controls(BaseWidget $widget): void;

    /**
     * Resolves this source's items for one widget render, from that
     * widget's settings array.
     *
     * @param array $settings
     * @return LoopResultDTO
     */
    public function get_items(array $settings): LoopResultDTO;

    /**
     * Validates and normalizes a decoded AJAX pagination request's settings —
     * attacker-controlled, since they round-tripped through the browser — into
     * the shape get_items expects for the given page.
     *
     * @param array $raw_settings
     * @param int   $paged
     * @return array
     */
    public function sanitize_ajax_settings(array $raw_settings, int $paged): array;

    /**
     * Adds this source's own Preview Settings controls to a Loop Item
     * document, each scoped with `condition => ['preview_data_source' =>
     * $this->get_key]` so it is only visible when this source is chosen as the
     * document's preview source.
     *
     * @param PreviewablePageBase $document
     * @return void
     */
    public function register_preview_controls(PreviewablePageBase $document): void;

    /**
     * Resolves one representative item for standalone editor preview, from a
     * Loop Item document's settings array.
     *
     * @param array $settings
     * @return LoopItemInterface|null
     */
    public function resolve_preview_item(array $settings): ?LoopItemInterface;
}
