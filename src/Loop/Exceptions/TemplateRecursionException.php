<?php

namespace Elemacy\Loop\Exceptions;

defined('ABSPATH') || exit;

use Exception;

/**
 * Thrown by TemplateRecursionGuard when a template would recurse into itself or exceed the nesting depth limit.
 *
 * @internal
 */
final class TemplateRecursionException extends Exception
{
}
