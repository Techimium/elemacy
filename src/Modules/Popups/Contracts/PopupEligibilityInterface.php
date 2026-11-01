<?php

namespace Elemacy\Modules\Popups\Contracts;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\DTO\PopupDTO;

/**
 * Whether a popup may be delivered to the current visitor at all: published,
 * not password-protected, with content, visible to the visitor's
 * capabilities, and with every condition it uses available. Which pages it
 * targets is not part of this check.
 *
 * @since 1.0.0
 * @api
 */
interface PopupEligibilityInterface
{
    public function is_runtime_eligible(PopupDTO $popup): bool;
}
