<?php

namespace Elemacy\Http\Contracts;

defined('ABSPATH') || exit;

/**
 * Contract for middleware that can intercept and authorize API requests.
 *
 * @since 1.0.0
 */
interface Middleware
{
    /**
     * Handle an incoming request and return a boolean indicating access.
     *
     * @since 1.0.0
     *
     * @param Request  $request The incoming request instance.
     * @param callable $next The next middleware in the chain.
     * @return mixed The result of the next middleware or a redirect response.
     */
    public function handle(Request $request, callable $next);
}
