<?php

namespace Elemacy\Http\Exceptions;

defined('ABSPATH') || exit;

use Elemacy\Http\Response;

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
