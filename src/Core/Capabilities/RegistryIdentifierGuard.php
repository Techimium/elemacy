<?php

namespace Elemacy\Core\Capabilities;

defined('ABSPATH') || exit;

/**
 * Shared identifier validation for the plugin's domain registries
 * (TemplateLibrary\TypeRegistry, Loop\LoopDataSourceRegistry,
 * Conditions\ConditionManager, Popups\Services\{Rule,Trigger}Manager).
 * Each registry keeps its own store and lookup semantics (design.md D10:
 * "keep responsibilities in their own domain registries"); this only
 * centralizes the two checks every one of them needs at registration time.
 *
 * Free deliberately registers a Mock* stand-in under the same identifier a
 * Pro extension later provides for real (see MockCondition, MockRule,
 * MockTrigger) — that same-name override is the intended extension
 * mechanism, not a collision. A registry with no such stand-in concept
 * (TypeRegistry, LoopDataSourceRegistry) instead tolerates re-registering
 * an identical entry (same class, same field values — PHP's loose object
 * equality) under a key it already owns, since an uncertain bootstrap order
 * can legitimately register a plain value-object type/source more than
 * once. What is never allowed is two genuinely different implementations
 * silently fighting over one identifier, or an identifier that collides
 * with a discriminator/structural key used in every one of these domains'
 * own wire formats.
 */
final class RegistryIdentifierGuard
{
    private const RESERVED_IDENTIFIERS = ['', 'type', 'id', 'unknown'];

    /**
     * @throws \InvalidArgumentException When the identifier collides with a
     *                                   discriminator/structural key used in
     *                                   these domains' own wire formats.
     */
    public static function assert_not_reserved(string $domain, string $identifier): void
    {
        if (in_array(strtolower(trim($identifier)), self::RESERVED_IDENTIFIERS, true)) {
            // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, never rendered as HTML output; a same-line ignore cannot reach a violation reported on a later line of this one statement.
            throw new \InvalidArgumentException(sprintf(
                '%s identifier "%s" is reserved and cannot be registered.',
                $domain,
                $identifier
            ));
            // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    /**
     * @param string      $domain     Human-readable registry name for the error message (e.g. "Condition").
     * @param string      $identifier The identifier the incoming entry is trying to claim.
     * @param object|null $existing   The entry currently registered under $identifier, if any.
     * @param object      $incoming   The entry attempting to register.
     *
     * @throws \InvalidArgumentException When the identifier is reserved, or already
     *                                   claimed by a different, non-mock implementation.
     */
    public static function assert_registrable(string $domain, string $identifier, ?object $existing, object $incoming): void
    {
        self::assert_not_reserved($domain, $identifier);

        if ($existing === null || $existing === $incoming) {
            return;
        }

        $existing_is_mock = method_exists($existing, 'is_mock') && $existing->is_mock();
        $incoming_is_mock = method_exists($incoming, 'is_mock') && $incoming->is_mock();
        // Loose object equality: same class AND equal property values. This
        // treats re-declaring an identical plain descriptor (TypeDefinition)
        // or a stateless service re-registered by an uncertain bootstrap
        // order (e.g. AcfRepeaterDataSource) as idempotent, while two
        // distinct classes — or the same class with different field values —
        // claiming one identifier is a genuine conflict.
        // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- deliberate: PHP's loose `==` on two objects compares class + all property values, which `===` (identity) cannot express.
        $same_registration = $existing == $incoming;

        if ($existing_is_mock || $incoming_is_mock || $same_registration) {
            return;
        }

        // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, never rendered as HTML output; a same-line ignore cannot reach a violation reported on a later line of this one statement.
        throw new \InvalidArgumentException(sprintf(
            '%s identifier "%s" is already registered by %s; refusing to replace it with %s.',
            $domain,
            $identifier,
            get_class($existing),
            get_class($incoming)
        ));
        // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }
}
