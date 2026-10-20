<?php

namespace Elemacy\Forms;

use Elemacy\Http\Exceptions\ValidationException;

defined('ABSPATH') || exit;

/**
 * The requested form does not exist or may not be used by this visitor.
 * Deliberately one message for every reason, so a response never reveals
 * which posts or forms exist.
 */
final class FormUnavailableException extends ValidationException
{
    public static function create(): self
    {
        return new self(__('This form is unavailable. Please refresh the page.', 'elemacy'), 400); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text JSON; the form runtime inserts it with textContent.
    }
}
