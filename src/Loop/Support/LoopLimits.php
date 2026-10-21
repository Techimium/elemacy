<?php

namespace Elemacy\Loop\Support;

defined('ABSPATH') || exit;

/**
 * Limits every nested-loop path shares: rendering, editor preview, signed
 * later-page context and rebuilding an item address all stop at the same depth.
 */
final class LoopLimits
{
    /** How many loop items (and loop templates) may be active inside one another. */
    public const MAX_DEPTH = 10;
}
