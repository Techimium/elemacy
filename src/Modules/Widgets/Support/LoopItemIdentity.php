<?php

namespace Elemacy\Modules\Widgets\Support;

defined('ABSPATH') || exit;

/**
 * Sanitizes a LoopItemInterface::get_identity() value down to characters
 * safe to interpolate into a CSS class name and an HTML id attribute — the
 * one place every item class should route its identity through, so a
 * future non-post source (a repeater row key, a term ID, an API item's own
 * id field) can never leak unsafe characters into generated markup or CSS
 * (design.md D4, Risks).
 */
final class LoopItemIdentity
{
    public static function sanitize(string $raw): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $raw);
    }
}
