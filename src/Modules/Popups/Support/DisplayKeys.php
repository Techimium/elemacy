<?php

namespace Elemacy\Modules\Popups\Support;

defined('ABSPATH') || exit;

/**
 * Layout/behavior control keys shared by PopupDocument (writer) and
 * DocumentDisplay (reader) — a persisted contract, so renaming a literal in only
 * one place would orphan saved popups. Style-tab keys aren't here: Elementor's
 * own CSS consumes them via `selectors`, so they have no separate reader.
 */
final class DisplayKeys
{
    const POSITION        = 'elemacy_position';
    const STICKY          = 'elemacy_sticky';
    const WIDTH           = 'elemacy_width';
    const HEIGHT          = 'elemacy_height';
    const Z_INDEX         = 'elemacy_z_index';

    const OVERLAY_ENABLED = 'elemacy_overlay_enabled';
    const OVERLAY_COLOR   = 'elemacy_overlay_color';
    const OVERLAY_OPACITY = 'elemacy_overlay_opacity';

    const ANIMATION_IN    = 'elemacy_animation_in';
    const ANIMATION_OUT   = 'elemacy_animation_out';

    const CLOSE_BUTTON    = 'elemacy_close_button';
    const AUTO_CLOSE      = 'elemacy_auto_close';
    const CLOSE_ON_OVERLAY = 'elemacy_close_on_overlay';
    const CLOSE_ON_ESC    = 'elemacy_close_on_esc';
    const PREVENT_SCROLL  = 'elemacy_prevent_scroll';
}
