<?php

namespace Elemacy\Core\Capabilities;

defined('ABSPATH') || exit;

/**
 * Shared identifier validation for the plugin's domain registries
 * (TemplateLibrary\TypeRegistry, Loop\LoopDataSourceRegistry,
 * Conditions\ConditionManager, Popups\Services\{Rule,Trigger}Manager).
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
     * Replacement is directional. A real implementation may supersede a mock
     * stand-in — that is how an add-on takes over the identifier its free
     * counterpart holds a placeholder for. The reverse never is: a mock
     * displacing a working implementation only ever silently downgrades a
     * live feature, so it is rejected rather than accepted on registration
     * order alone.
     *
     * @param string      $domain     Human-readable registry name for the error message (e.g. "Condition").
     * @param string      $identifier The identifier the incoming entry is trying to claim.
     * @param object|null $existing   The entry currently registered under $identifier, if any.
     * @param object      $incoming   The entry attempting to register.
     *
     * @throws \InvalidArgumentException When the identifier is reserved, already
     *                                   claimed by a different real implementation,
     *                                   or claimed by a real implementation the
     *                                   incoming mock would displace.
     */
    public static function assert_registrable(string $domain, string $identifier, ?object $existing, object $incoming): void
    {
        self::assert_not_reserved($domain, $identifier);

        if ($existing === null || $existing === $incoming) {
            return;
        }

        // Anything may supersede a mock: the real implementation it stands
        // in for, or another stand-in.
        if (self::is_mock($existing)) {
            return;
        }

        // Loose object equality: same class AND equal property values. This
        // treats re-declaring an identical plain descriptor (TypeDefinition)
        // or a stateless service re-registered by an uncertain bootstrap
        // order (e.g. AcfRepeaterDataSource) as a no-op, while two
        // distinct classes — or the same class with different field values —
        // claiming one identifier is a genuine conflict.
        // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- deliberate: PHP's loose `==` on two objects compares class + all property values, which `===` (identity) cannot express.
        if ($existing == $incoming) {
            return;
        }

        // Past this point $existing is a real implementation and $incoming is
        // a different entry: a mock trying to displace it, or a second real
        // implementation colliding with it. Both are rejected, and the
        // message distinguishes them.
        $reason = self::is_mock($incoming)
            ? 'refusing to replace it with the unavailable stand-in %s.'
            : 'refusing to replace it with %s.';

        // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, never rendered as HTML output; a same-line ignore cannot reach a violation reported on a later line of this one statement.
        throw new \InvalidArgumentException(sprintf(
            '%s identifier "%s" is already registered by %s; ' . $reason,
            $domain,
            $identifier,
            get_class($existing),
            get_class($incoming)
        ));
        // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    /**
     * Whether an entry reports itself as a non-functional stand-in. An entry
     * from a domain that has no such concept is never one.
     */
    private static function is_mock(object $entry): bool
    {
        return method_exists($entry, 'is_mock') && $entry->is_mock();
    }
}
