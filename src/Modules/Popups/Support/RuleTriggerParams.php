<?php

namespace Elemacy\Modules\Popups\Support;

defined('ABSPATH') || exit;

use Elemacy\Core\Exceptions\ValidationException;

/**
 * Shared write-boundary validation for RuleDTO/TriggerDTO `params` — the one
 * field an editor payload fully controls (task 10.2, design.md D10:
 * "Reserve discriminator keys ... so parameter arrays cannot overwrite
 * them"). `type`/`id` select which rule/trigger implementation runs and its
 * stored identity; a params entry using either key is never a legitimate
 * schema field (see Rules/Triggers Config/*.php in both plugins — no
 * get_config_schema() anywhere declares one) and is always an attempt to
 * shadow the discriminator once BaseRule/BaseTrigger::to_js_config() spreads
 * params into the client-facing config object.
 *
 * Every schema currently registered only ever declares a scalar field
 * (number/text/select/datetime) or a flat list of scalars (checkbox-group),
 * so rejecting anything deeper is safe without this class needing to know
 * which shape a given key expects.
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
