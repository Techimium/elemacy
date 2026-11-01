<?php

namespace Elemacy\Modules\Popups\Contracts;

defined('ABSPATH') || exit;

use Elemacy\Http\Exceptions\NotFoundException;
use Elemacy\Modules\Popups\DTO\PopupDTO;

/**
 * Reads a stored popup.
 *
 * @since 1.0.0
 * @api
 */
interface PopupRepositoryInterface
{
    /**
     * @throws NotFoundException When $id is not a popup.
     */
    public function get_or_fail(int $id): PopupDTO;
}
