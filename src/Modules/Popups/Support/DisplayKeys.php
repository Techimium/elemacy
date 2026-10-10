<?php

namespace Elemacy\Modules\Popups\Support;

defined('ABSPATH') || exit;

/**
 * Layout/behavior control keys shared by PopupDocument (writer) and
 * DocumentDisplay (reader) — a persisted contract, so renaming a literal in
 * only one place would orphan saved popups.
 */
final class DisplayKeys
{
    public const POSITION        = 'elemacy_position';
    public const STICKY          = 'elemacy_sticky';
    public const WIDTH           = 'elemacy_width';
    public const Z_INDEX         = 'elemacy_z_index';
    public const OFFSET_X        = 'elemacy_offset_x';
    public const OFFSET_Y        = 'elemacy_offset_y';

    public const OVERLAY_COLOR   = 'elemacy_overlay_color';
    public const OVERLAY_OPACITY = 'elemacy_overlay_opacity';
    public const OVERLAY_BLUR    = 'elemacy_overlay_blur';

    public const ANIMATION_IN    = 'elemacy_animation_in';
    public const ANIMATION_OUT   = 'elemacy_animation_out';

    public const CLOSE_BUTTON    = 'elemacy_close_button';
    public const AUTO_CLOSE      = 'elemacy_auto_close';
    public const CLOSE_ON_OVERLAY = 'elemacy_close_on_overlay';
    public const CLOSE_ON_ESC    = 'elemacy_close_on_esc';
    public const PREVENT_SCROLL  = 'elemacy_prevent_scroll';
}
