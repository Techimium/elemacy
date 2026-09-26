<?php

namespace Elemacy\Http\Exceptions;

defined('ABSPATH') || exit;

use Elemacy\Http\Response;
use Exception;

/**
 * Exception carrying an HTTP status, consumed by ApiExceptionHandler which uses
 * getCode() as the response status.
 *
 * @since 1.0.0
 */
class HttpException extends Exception
{
    // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found -- Changes the default code from 0 to 500.
    public function __construct(string $message = '', int $status = Response::INTERNAL_SERVER_ERROR)
    {
        parent::__construct($message, $status);
    }
}
