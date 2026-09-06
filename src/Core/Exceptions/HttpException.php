<?php

namespace Elemacy\Core\Exceptions;

defined('ABSPATH') || exit;

use Elemacy\Core\Http\Response;
use Exception;

/**
 * Exception carrying an HTTP status, consumed by ApiExceptionHandler which uses
 * getCode() as the response status.
 *
 * @since 1.0.0
 */
class HttpException extends Exception
{
    public function __construct(string $message = '', int $status = Response::INTERNAL_SERVER_ERROR)
    {
        parent::__construct($message, $status);
    }
}
