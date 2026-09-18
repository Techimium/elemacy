<?php

namespace Elemacy\Modules\DynamicTags\Services;

defined('ABSPATH') || exit;

use Elemacy\Integrations\Acf\AcfFieldReader;

/**
 * Moved to Elemacy\Integrations\Acf\AcfFieldReader (design.md D7's
 * Integrations/Acf extraction), as an instance rather than a static class so
 * new callers can receive it through their own constructor. This static
 * facade is kept here, unchanged in calling convention, for every existing
 * `Acf::get_field_value()` call site across both plugins (Dynamic Tags' own
 * tags included) — not a class_alias() like the other moves in this phase,
 * since the canonical class's methods are no longer static at all.
 *
 * @deprecated Use Elemacy\Integrations\Acf\AcfFieldReader.
 */
class Acf
{
    public static function get_field_value($key, $post_id = false)
    {
        return (new AcfFieldReader())->get_field_value((string) $key, $post_id);
    }
}
