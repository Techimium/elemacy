<?php

namespace Elemacy\Modules\Popups\Triggers\Placeholders;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Triggers\BaseTrigger;

/**
 * A Pro placeholder: lists a trigger that only Pro implements, so users can
 * see what Pro adds. It declares a name, a label and a settings shape and
 * never contains the feature's logic.
 *
 * @internal
 */
abstract class PlaceholderTrigger extends BaseTrigger
{
    public function is_placeholder(): bool
    {
        return true;
    }
}
