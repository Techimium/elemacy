<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

use Elemacy\Core\Documents\PreviewablePageBase;

/**
 * The standalone-preview half of a loop data source (design.md D7): a
 * source that supports being chosen as a Loop Item document's own preview
 * source implements this in addition to the query/controls contracts.
 */
interface LoopSourcePreviewInterface
{
    /**
     * Adds this source's own Preview Settings controls to a Loop Item
     * document, each scoped with `condition => ['preview_data_source' =>
     * $key]` so it is only visible when this source is chosen as the
     * document's preview source. Mirrors LoopSourceControlsInterface's own
     * register_controls() but targets a document's Preview Settings section
     * instead of a widget's Query section.
     *
     * @param PreviewablePageBase $document
     * @return void
     */
    public function register_preview_controls(PreviewablePageBase $document): void;

    /**
     * Resolves one representative item for standalone editor preview, from
     * a Loop Item document's settings array. Returns null when no item can
     * be resolved (e.g. an empty taxonomy, no matching users, an empty or
     * unavailable ACF repeater) — callers must treat that as "nothing to
     * preview", not an error.
     *
     * @param array $settings
     * @return \Elemacy\Loop\Contracts\LoopItemInterface|null
     */
    public function resolve_preview_item(array $settings): ?LoopItemInterface;
}
