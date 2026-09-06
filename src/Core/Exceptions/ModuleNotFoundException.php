<?php

namespace Elemacy\Core\Exceptions;

defined('ABSPATH') || exit;

/**
 * Thrown when a module slug doesn't resolve. Extends HttpException (thrown with
 * a 404 status) so the message and status reach the client.
 */
class ModuleNotFoundException extends HttpException
{
}
