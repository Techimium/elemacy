<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

use Elemacy\Elementor\Documents\PreviewablePageBase;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Elementor\Widgets\BaseWidget;

/**
 * Supplies the items a Loop Grid / Loop Carousel widget renders.
 *
 * Frozen from 1.0: these methods and their meanings never change. A new
 * ability arrives as a separate optional contract a source may also implement
 * (PublicLoopSourcePolicyInterface, PageUrlPaginatedSourceInterface, ...).
 * Visitors only ever see a source that implements
 * PublicLoopSourcePolicyInterface; without it the loop renders for editors
 * only. An extension's control IDs start with its own source key, because
 * every source's controls share the widget's one settings namespace.
 */
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
     * scoped with `condition => ['data_source' => $this->get_key()]` (in
     * addition to whatever conditions the control itself needs) so it is
     * only visible when this source is selected.
     *
     * @param BaseWidget $widget
     * @return void
     */
    public function register_controls(BaseWidget $widget): void;

    /**
     * Resolves this source's items for one render: the editor passes the
     * widget's settings, a visitor's render passes what
     * PublicLoopSourcePolicyInterface::get_public_settings() returned.
     *
     * @param array $settings
     * @return LoopResultDTO
     */
    public function get_items(array $settings): LoopResultDTO;

    /**
     * Adds this source's own Preview Settings controls to a Loop Item
     * document, each scoped with `condition => ['preview_data_source' =>
     * $this->get_key()]` so it is only visible when this source is chosen as the
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
