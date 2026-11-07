<?php

namespace Elemacy\Http\Middlewares;

defined('ABSPATH') || exit;

use Elemacy\Http\Contracts\MiddlewareInterface;
use Elemacy\Http\Contracts\RequestInterface;
use Elemacy\Http\Exceptions\AuthorizationException;

/**
 * Middleware to ensure the user can manage options.
 *
 * @since 1.0.0
 * @api
 */
class AdminMiddleware implements MiddlewareInterface
{
    /**
     * Handle the incoming request and determine if the user can manage options.
     *
     * @since 1.0.0
     *
     * @param RequestInterface $request The incoming request instance.
     * @param callable         $next The next middleware in the chain.
     * @return mixed The result of the next middleware or a response.
     */
    public function handle(RequestInterface $request, callable $next)
    {
        if (current_user_can('manage_options')) {
            return $next($request);
        }

        throw new AuthorizationException(esc_html__('You do not have permission for this action', 'elemacy'));
    }
}
