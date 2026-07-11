<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

use Elemacy\Core\Http\Response;
use Elemacy\Core\Exceptions\HttpException;
use Elemacy\Core\Exceptions\ValidationException;
use Exception;

class ApiExceptionHandler
{
    /**
     * Converts a controller-thrown exception into a REST response.
     *
     * @param Exception $exception The exception raised while handling the request.
     * @return Response
     */
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

    /**
     * Builds the response for any non-ValidationException. HttpExceptions carry
     * a deliberate, client-safe message and status; everything else is logged
     * and replaced with a generic message.
     *
     * @param Exception $exception The exception raised while handling the request.
     * @return Response
     */
    protected static function fallback_response(Exception $exception)
    {
        // HttpExceptions are deliberate, client-facing errors carrying a
        // translated message and an HTTP status, so surface them as-is.
        if ($exception instanceof HttpException) {
            $status_code = (int) $exception->getCode();

            if ($status_code < 100 || $status_code > 599) {
                $status_code = Response::INTERNAL_SERVER_ERROR;
            }

            return (new Response())->json(['message' => $exception->getMessage()], $status_code);
        }

        // Anything else is unexpected — never leak the raw message (it can carry
        // class names, paths, or third-party detail). Log it for debugging and
        // return a generic message.
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Elemacy] ' . get_class($exception) . ': ' . $exception->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }

        return (new Response())->json(
            ['message' => __('Something went wrong. Please try again.', 'elemacy')],
            Response::INTERNAL_SERVER_ERROR
        );
    }
}
