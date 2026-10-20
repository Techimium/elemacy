<?php

namespace Elemacy\Modules\Popups\Support;

defined('ABSPATH') || exit;

/**
 * Browser storage keys the popup runtime (and Elemacy Pro) write in visitors'
 * browsers. Every key starts with the current site's id, so two sites of a
 * multisite network sharing one browser origin never read or change each
 * other's counts. The templates are permanent identifiers: a visitor's saved
 * frequency and visit counts are found by them.
 *
 * @api Pro builds its visit-count keys from prefix().
 */
final class StorageKeys
{
    /** Every key's start; `{blog}` is the site id. */
    public const PREFIX_TEMPLATE = 'elemacy_{blog}_';

    /** One popup's frequency and once-per counts (`localStorage` and `sessionStorage`). */
    public const POPUP_TEMPLATE = 'elemacy_{blog}_popup_{id}';

    /** The current site's (or $blog_id's) key prefix, e.g. `elemacy_1_`. */
    public static function prefix(?int $blog_id = null): string
    {
        return strtr(self::PREFIX_TEMPLATE, ['{blog}' => (string) ($blog_id ?? get_current_blog_id())]);
    }

    /** Popup $popup_id's key on the current site (or $blog_id), e.g. `elemacy_1_popup_42`. */
    public static function popup(int $popup_id, ?int $blog_id = null): string
    {
        return strtr(self::POPUP_TEMPLATE, [
            '{blog}' => (string) ($blog_id ?? get_current_blog_id()),
            '{id}' => (string) $popup_id,
        ]);
    }
}
