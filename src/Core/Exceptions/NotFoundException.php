<?php

namespace Elemacy\Core\Exceptions;

defined('ABSPATH') || exit;

use Elemacy\Core\Http\Response;

/**
 * Exception thrown when a requested resource does not exist.
 *
 * @since 1.0.0
 */
class NotFoundException extends HttpException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message, Response::NOT_FOUND);
    }
}
