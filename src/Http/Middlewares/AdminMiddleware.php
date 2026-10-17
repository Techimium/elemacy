<?php

namespace Elemacy\Http\Middlewares;

defined('ABSPATH') || exit;

use Elemacy\Http\Contracts\Middleware;
use Elemacy\Http\Contracts\Request;
use Elemacy\Http\Exceptions\AuthorizationException;

/**
 * Middleware to ensure the user can manage options.
 *
 * @since 1.0.0
 */
class AdminMiddleware implements Middleware
{
    /**
     * Handle the incoming request and determine if the user can manage options.
     *
     * @since 1.0.0
     *
     * @param Request  $request The incoming request instance.
     * @param callable $next The next middleware in the chain.
     * @return mixed The result of the next middleware or a response.
     */
    public function handle(Request $request, callable $next)
    {
        if (current_user_can('manage_options')) {
            return $next($request);
        }

        throw new AuthorizationException(esc_html__('You do not have permission for this action', 'elemacy'));
    }
}
