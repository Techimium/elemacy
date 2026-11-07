<?php

namespace Elemacy\Http\Middlewares;

defined('ABSPATH') || exit;

use Elemacy\Http\Contracts\MiddlewareInterface;
use Elemacy\Http\Contracts\RequestInterface;
use Elemacy\Http\Exceptions\AuthorizationException;

/**
 * Middleware to ensure the user is authenticated.
 *
 * @since 1.0.0
 * @api
 */
class AuthMiddleware implements MiddlewareInterface
{
    /**
     * Handle the incoming request and determine if the user is authenticated.
     *
     * @since 1.0.0
     *
     * @param RequestInterface $request The incoming request instance.
     * @return mixed The result of the next middleware or a response.
     */
    public function handle(RequestInterface $request, callable $next)
    {
        if (is_user_logged_in()) {
            return $next($request);
        }

        throw new AuthorizationException(esc_html__('You have to be logged in', 'elemacy'));
    }
}
