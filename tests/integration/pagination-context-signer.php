<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\Pagination\PaginationContextSigner;

require_once __DIR__ . '/IntegrationTestCase.php';

final class PaginationContextSignerTest extends IntegrationTestCase
{
    private PaginationContextSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signer = new PaginationContextSigner();
    }

    public function testValidTokenRoundTripsBoundFacts(): void
    {
        $token = $this->signer->sign(101, 'w1', str_repeat('a', 64), 'page', ['post_id' => 101]);
        self::assertMatchesRegularExpression(
            '/\A[A-Za-z0-9_-]+\.[a-f0-9]{64}\z/',
            $token,
            "Token must satisfy PublicLoopRequest's context shape."
        );

        $claims = $this->signer->verify($token, 101, 'w1', str_repeat('a', 64));
        self::assertSame('page', $claims['type']);
        self::assertSame(['post_id' => 101], $claims['facts']);
    }

    /** @dataProvider mismatchedBindings */
    public function testTokenIsBoundToOwnerWidgetAndDefinitionRevision(int $owner, string $widget, string $fingerprint): void
    {
        $token = $this->signer->sign(101, 'w1', str_repeat('a', 64), 'page', ['post_id' => 101]);
        $this->expectException(ValidationException::class);
        $this->signer->verify($token, $owner, $widget, $fingerprint);
    }

    public static function mismatchedBindings(): array
    {
        return [
            'different owner' => [202, 'w1', str_repeat('a', 64)],
            'different widget' => [101, 'w2', str_repeat('a', 64)],
            'different revision' => [101, 'w1', str_repeat('b', 64)],
        ];
    }

    public function testForgedNestingLineageFailsSignatureVerification(): void
    {
        $token = $this->signer->sign(101, 'w1', str_repeat('a', 64), 'nested_loop', ['lineage' => [1, 2]]);
        $forged = $this->tamperFacts($token, ['lineage' => [1, 2, 3]]);
        $this->expectException(ValidationException::class);
        $this->signer->verify($forged, 101, 'w1', str_repeat('a', 64));
    }

    public function testChangedArchiveFactsFailSignatureVerification(): void
    {
        $token = $this->signer->sign(101, 'w1', str_repeat('a', 64), 'theme_builder', [
            'location' => 'archive', 'query' => ['post_type' => ['post']],
        ]);
        $forged = $this->tamperFacts($token, [
            'location' => 'archive', 'query' => ['post_type' => ['page']],
        ]);
        $this->expectException(ValidationException::class);
        $this->signer->verify($forged, 101, 'w1', str_repeat('a', 64));
    }

    public function testCrossSiteTokenIsRejectedDespiteAValidSignature(): void
    {
        // Even a signature computed with this site's own salt (e.g. a shared
        // network salt) must not authorize a claim bound to another blog.
        $foreign = $this->signWithBlogId(get_current_blog_id() + 1, 101, 'w1', str_repeat('a', 64), 'page', ['post_id' => 101]);
        $this->expectException(ValidationException::class);
        $this->signer->verify($foreign, 101, 'w1', str_repeat('a', 64));
    }

    /** @dataProvider malformedTokens */
    public function testMalformedTokensAreRejected(string $token): void
    {
        $this->expectException(ValidationException::class);
        $this->signer->verify($token, 101, 'w1', str_repeat('a', 64));
    }

    public static function malformedTokens(): array
    {
        return [
            'empty' => [''],
            'no separator' => ['no-dot-here'],
            'unsigned payload' => ['payload.' . str_repeat('0', 64)],
            'non-base64url payload' => ['not base64!.' . str_repeat('a', 64)],
        ];
    }

    /** Re-shapes an existing token's facts without a valid signature for them — an attacker cannot mint one. */
    private function tamperFacts(string $token, array $facts): string
    {
        [$encoded, $signature] = explode('.', $token, 2);
        $payload = json_decode($this->decode($encoded), true);
        $payload['facts'] = $facts;
        $tampered = $this->encode((string) wp_json_encode($payload));

        return $tampered . '.' . $signature;
    }

    /** Builds a token exactly like the real signer would, but bound to a different blog. */
    private function signWithBlogId(int $blog_id, int $owner_id, string $widget_id, string $fingerprint, string $type, array $facts): string
    {
        $encoded = $this->encode((string) wp_json_encode([
            'blog_id' => $blog_id, 'owner_id' => $owner_id, 'widget_id' => $widget_id,
            'fingerprint' => $fingerprint, 'type' => $type, 'facts' => $facts,
        ]));

        return $encoded . '.' . hash_hmac('sha256', $encoded, wp_salt('auth') . '|elemacy_loop_context');
    }

    private function encode(string $json): string
    {
        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    private function decode(string $encoded): string
    {
        return (string) base64_decode(strtr($encoded, '-_', '+/'), true);
    }
}
