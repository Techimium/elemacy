<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\Rendering\ItemPlacement;

/**
 * How one kind of loop widget turns a loop item into markup, and whether what
 * its saved settings point at may be shown to visitors. The loop engine calls
 * only this, so the first page, the pagination endpoint and the public policy
 * never need to know what a given widget renders its items with.
 *
 * @internal
 */
interface ItemPresenterInterface
{
    /**
     * @param array $settings Saved widget settings. May be missing every value left at its default.
     * @return void
     * @throws ValidationException If these settings cannot render for visitors.
     */
    public function assert_public(array $settings): void;

    /**
     * Why assert_public() would refuse these settings, in a sentence for
     * someone who can edit the document; null when it would not.
     *
     * @param array $settings Saved widget settings.
     * @return string|null
     */
    public function describe_unavailable(array $settings): ?string;

    /**
     * Prints one item, returning whether it rendered. A failure must leave no
     * output and must not throw.
     *
     * @param LoopItemInterface $item
     * @param array             $settings Saved widget settings. May be missing every value left at its default.
     * @param ItemPlacement     $placement Where this item sits in the current render.
     * @return bool
     */
    public function render_item(LoopItemInterface $item, array $settings, ItemPlacement $placement): bool;
}
