<?php

namespace Elemacy\Modules\Popups\Support;

defined('ABSPATH') || exit;

use Elemacy\Http\Exceptions\ValidationException;

/**
 * Shared write-boundary validation for RuleDTO/TriggerDTO `params` — the one
 * field an editor payload fully controls.
 */
final class RuleTriggerParams
{
    private const RESERVED_KEYS = ['type', 'id'];

    /**
     * @throws ValidationException
     */
    public static function assert_valid(string $field, array $params): void
    {
        foreach ($params as $key => $value) {
            if (in_array($key, self::RESERVED_KEYS, true)) {
                $message = sprintf(
                    /* translators: %s: the reserved parameter key. */
                    esc_html__('Parameter "%s" is reserved and cannot be set.', 'elemacy'),
                    $key
                );

                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message (already esc_html()'d above), never rendered as HTML output.
                throw ValidationException::with_errors([$field => [$message]]);
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if (is_array($item) || is_object($item)) {
                        throw ValidationException::with_errors([
                            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, never rendered as HTML output.
                            $field => [esc_html__('One or more parameters has an unsupported shape.', 'elemacy')],
                        ]);
                    }
                }
            } elseif (is_object($value) || is_resource($value)) {
                throw ValidationException::with_errors([
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, never rendered as HTML output.
                    $field => [esc_html__('One or more parameters has an unsupported shape.', 'elemacy')],
                ]);
            }
        }
    }
}
