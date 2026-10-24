<?php

namespace Elemacy\Modules\Popups\Support;

defined('ABSPATH') || exit;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Modules\Popups\Services\RuleManager;
use Elemacy\Modules\Popups\Services\TriggerManager;

/**
 * Shared write-boundary validation for RuleDTO/TriggerDTO `params` — the one
 * field an editor payload fully controls.
 */
final class RuleTriggerParams
{
    private const RESERVED_KEYS = ['type', 'id'];

    /**
     * Validates and sanitizes the `triggers` and `rules` of a create or update
     * payload: every type must be registered (a placeholder for an extension
     * that is not active counts, so a popup built with Pro can still be saved
     * without it), and every parameter must be a supported shape.
     *
     * @param array $data The request's cleaned data.
     * @return array The same data with sanitized parameters.
     * @throws ValidationException When a type is not registered or a parameter has an unsupported shape.
     */
    public static function clean_payload(array $data): array
    {
        $managers = [
            'triggers' => TriggerManager::instance(),
            'rules' => RuleManager::instance(),
        ];

        foreach ($managers as $field => $manager) {
            if (!isset($data[$field]) || !is_array($data[$field])) {
                continue;
            }

            foreach ($data[$field] as $index => $item) {
                $type = is_array($item) ? (string) ($item['type'] ?? '') : '';

                if (null === $manager->get($type)) {
                    $message = sprintf(
                        /* translators: 1: "trigger" or "rule", 2: the unknown type. */
                        esc_html__('Unknown %1$s type "%2$s".', 'elemacy'),
                        'triggers' === $field ? esc_html__('trigger', 'elemacy') : esc_html__('rule', 'elemacy'),
                        esc_html($type)
                    );

                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message (escaped above), never rendered as HTML output.
                    throw ValidationException::with_errors([$field => [$message]]);
                }

                $params = is_array($item['params'] ?? null) ? $item['params'] : [];
                self::assert_valid($field, $params);
                $data[$field][$index]['params'] = self::sanitize($params);
            }
        }

        return $data;
    }

    /**
     * Cleans every text value in $params. Unlike sanitize_text_field() this
     * leaves percent-encoded sequences alone (a Referrer URL such as
     * `example.com/?q=a%20b` must round-trip), and still strips markup, control
     * characters and line breaks. Keys and non-text values are untouched.
     *
     * @param array $params
     * @return array
     */
    public static function sanitize(array $params): array
    {
        return map_deep($params, static function ($leaf) {
            if (!is_string($leaf)) {
                return $leaf;
            }

            $text = wp_check_invalid_utf8($leaf);
            $text = wp_strip_all_tags($text);
            $text = (string) preg_replace('/[\r\n\t ]+/', ' ', $text);
            $text = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $text);

            return trim($text);
        });
    }

    /**
     * @throws ValidationException When the params do not match the rule's or trigger's schema.
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
