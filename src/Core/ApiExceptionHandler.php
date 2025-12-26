<?php

namespace Elemacy\Core;

use Elemacy\Core\Http\Response;
use Elemacy\Core\Exceptions\ValidationException;
use Exception;

class ApiExceptionHandler
{
    public static function get_response(Exception $exception)
    {
        if ($exception instanceof ValidationException) {
            $response = [
                'message' => $exception->getMessage(),
                'errors' => $exception->get_errors(),
            ];

            return (new Response())->json($response, Response::UNPROCESSABLE_ENTITY);
        }

        return static::fallback_response($exception);
    }

    protected static function fallback_response(Exception $exception)
    {
        $status_code = (int) $exception->getCode();

        if ($status_code < 100 || $status_code > 599) {
            $status_code = Response::INTERNAL_SERVER_ERROR; // fallback to 500
        }

        $response = [
            'message' => $exception->getMessage(),
        ];

        return (new Response())->json($response, $status_code);
    }
}
