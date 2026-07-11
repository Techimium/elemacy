<?php

namespace Elemacy\Core\Exceptions;

defined('ABSPATH') || exit;

/**
 * Thrown when a route's action is misconfigured — a missing controller, an
 * unknown method, or malformed action syntax. Extends HttpException so it
 * carries a status and renders as a clean API error.
 */
class InvalidRouteActionException extends HttpException
{
}
