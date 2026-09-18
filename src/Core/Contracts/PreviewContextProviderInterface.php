<?php

namespace Elemacy\Core\Contracts;

defined('ABSPATH') || exit;

/**
 * Enters/leaves the native context a previewed item needs while it renders
 * in the Elementor editor (design.md D7) — the piece of
 * Core\Preview\PreviewManager's own orchestration that used to call directly
 * into a concrete Loop service. Core owns this contract; Loop\Preview\
 * supplies the implementation that actually knows about loop items, so Core
 * itself never imports a concrete Loop class.
 */
interface PreviewContextProviderInterface
{
    /**
     * @param mixed $item Whatever PreviewablePageBase::get_preview_item()
     *                     returned — opaque to Core, meaningful only to this
     *                     provider's own implementation.
     * @return mixed An opaque token passed back to leave() unchanged.
     */
    public function enter($item);

    /**
     * @param mixed $context The token enter() returned.
     * @return void
     */
    public function leave($context): void;
}
