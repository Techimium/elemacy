<?php

namespace Elemacy\Modules\Popups\Rules\Placeholders;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Rules\BaseRule;

/**
 * A Pro placeholder: lists an advanced rule that only Pro implements, so users can
 * see what Pro adds. It declares a name, a label and a settings shape and
 * never contains the feature's logic.
 *
 * @internal
 */
abstract class PlaceholderRule extends BaseRule
{
    public function is_placeholder(): bool
    {
        return true;
    }
}
