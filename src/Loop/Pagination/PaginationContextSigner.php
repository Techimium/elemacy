<?php

namespace Elemacy\Loop\Pagination;

defined('ABSPATH') || exit;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\Placement\HostFacts;
use JsonException;

/**
 * Signs/verifies the opaque host-context token a public pagination request
 * carries. See docs/ARCHITECTURE.md § Rationale digest.
 */
final class PaginationContextSigner
{
    private const PURPOSE = 'elemacy_loop_context';

    /**
     * @param int    $owner_id The document that owns the loop widget.
     * @param string $widget_id The loop widget's Elementor element id.
     * @param string $fingerprint The saved widget definition's fingerprint.
     * @param string $type Registered placement key (see PlacementResolverRegistry).
     * @param array  $facts Host facts already verified by that placement's resolver.
     * @return string Opaque token matching PublicLoopRequest's context shape.
     */
    public function sign(int $owner_id, string $widget_id, string $fingerprint, string $type, array $facts): string
    {
        $encoded = self::encode((string) wp_json_encode([
            'blog_id' => get_current_blog_id(),
            'host_id' => is_singular() ? get_queried_object_id() : 0,
            'expires' => time() + DAY_IN_SECONDS,
            'owner_id' => $owner_id,
            'widget_id' => $widget_id,
            'fingerprint' => $fingerprint,
            'type' => $type,
            'facts' => $facts,
        ]));

        return $encoded . '.' . self::mac($encoded);
    }

    /**
     * @param string $token Claimed opaque context from a validated PublicLoopRequest.
     * @return array{type: string, facts: array}
     * @throws ValidationException If the token is missing, tampered, or bound to another site/definition.
     */
    public function verify(string $token, int $owner_id, string $widget_id, string $fingerprint): array
    {
        $parts = explode('.', $token, 2);
        if (2 !== count($parts) || '' === $parts[0] || !hash_equals(self::mac($parts[0]), $parts[1])) {
            throw self::unavailable();
        }

        try {
            $claims = json_decode(self::decode($parts[0]), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw self::unavailable();
        }

        if (
            !is_array($claims)
            || ($claims['blog_id'] ?? null) !== get_current_blog_id()
            || ($claims['expires'] ?? 0) < time()
            || ($claims['owner_id'] ?? null) !== $owner_id
            || ($claims['widget_id'] ?? null) !== $widget_id
            || !isset($claims['fingerprint']) || !is_string($claims['fingerprint']) || !hash_equals($claims['fingerprint'], $fingerprint)
            || !isset($claims['type']) || !is_string($claims['type'])
            || !isset($claims['facts']) || !is_array($claims['facts'])
        ) {
            throw self::unavailable();
        }

        $host = (int) ($claims['host_id'] ?? 0);
        $lineage = $claims['type'] === 'nested_loop' ? ($claims['facts']['lineage'] ?? []) : [];
        if (
            !is_array($lineage) || count($lineage) > 10
            || ($claims['type'] === 'nested_loop' && (!$lineage || end($lineage) !== $owner_id))
        ) {
            throw self::unavailable();
        }
        // The host facts were public when signed; anything that stopped being
        // public since then must not be served from a still-valid token.
        $post_fact = array_key_exists(HostFacts::POST_ID, $claims['facts']) ? [$claims['facts'][HostFacts::POST_ID]] : [];
        foreach (array_unique(array_merge($host ? [$host] : [], $lineage, $post_fact)) as $id) {
            if (!is_int($id) || !HostFacts::is_public_post(get_post($id))) {
                throw self::unavailable();
            }
        }
        if (array_key_exists(HostFacts::TERM_ID, $claims['facts'])) {
            $term_id = $claims['facts'][HostFacts::TERM_ID];
            if (!is_int($term_id) || !HostFacts::is_public_term(get_term($term_id))) {
                throw self::unavailable();
            }
        }

        return [
            'type' => $claims['type'],
            'facts' => $claims['facts'],
        ];
    }

    private static function mac(string $encoded): string
    {
        return hash_hmac('sha256', $encoded, wp_salt('auth') . '|' . self::PURPOSE);
    }

    private static function encode(string $json): string
    {
        return rtrim(strtr(base64_encode($json), '+/', '-_'), '='); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL-safe encoding of a signed, public token.
    }

    /** @throws ValidationException On non-base64url input. */
    private static function decode(string $encoded): string
    {
        if (!preg_match('/\A[A-Za-z0-9_-]+\z/', $encoded)) {
            throw self::unavailable();
        }
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes the URL-safe token encoded above; its signature is verified before use.
        if (false === $decoded) {
            throw self::unavailable();
        }

        return $decoded;
    }

    private static function unavailable(): ValidationException
    {
        return PublicRenderPolicy::unavailable();
    }
}
