<?php

namespace Elemacy\Loop\Rendering;

defined('ABSPATH') || exit;

use Exception;

/** Thrown by TemplateRecursionGuard when a template would recurse into itself or exceed the nesting depth limit. */
final class TemplateRecursionException extends Exception
{
}
