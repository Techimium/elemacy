<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\Access\PaginationContextSigner;

require_once __DIR__ . '/IntegrationTestCase.php';

final class PaginationContextSignerTest extends IntegrationTestCase
{
    private PaginationContextSigner $signer;
    private array $posts = [];
    private array $terms = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->signer = new PaginationContextSigner();
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->posts as $id) {
                wp_delete_post($id, true);
            }
            foreach ($this->terms as $id) {
                wp_delete_term($id, 'category');
            }
        } finally {
            parent::tearDown();
        }
    }

    public function testValidTokenRoundTripsBoundFacts(): void
    {
        $post = $this->post();
        $term = $this->term();
        $token = $this->signer->sign(101, 'w1', str_repeat('a', 64), 'page', ['post_id' => $post, 'term_id' => $term]);
        self::assertMatchesRegularExpression(
            '/\A[A-Za-z0-9_-]+\.[a-f0-9]{64}\z/',
            $token,
            "Token must satisfy PublicLoopRequest's context shape."
        );

        $claims = $this->signer->verify($token, 101, 'w1', str_repeat('a', 64));
        self::assertSame('page', $claims['type']);
        self::assertSame(['post_id' => $post, 'term_id' => $term], $claims['facts']);
    }

    /** @dataProvider postsThatStopBeingPublic */
    public function testACurrentPostThatStoppedBeingPublicIsRejected(callable $change): void
    {
        $post = $this->post();
        $token = $this->signer->sign(101, 'w1', str_repeat('a', 64), 'theme_builder', ['location' => 'single', 'post_id' => $post]);
        self::assertSame($post, $this->signer->verify($token, 101, 'w1', str_repeat('a', 64))['facts']['post_id'], 'Valid while public.');
        $change($post);

        $this->expectException(ValidationException::class);
        $this->signer->verify($token, 101, 'w1', str_repeat('a', 64));
    }

    public static function postsThatStopBeingPublic(): array
    {
        return [
            'trashed' => [static function (int $id): void { wp_trash_post($id); }],
            'made private' => [static function (int $id): void { wp_update_post(['ID' => $id, 'post_status' => 'private']); }],
            'password protected' => [static function (int $id): void { wp_update_post(['ID' => $id, 'post_password' => 'secret']); }],
        ];
    }

    public function testADeletedCurrentTermIsRejected(): void
    {
        $term = $this->term();
        $token = $this->signer->sign(101, 'w1', str_repeat('a', 64), 'theme_builder', ['location' => 'archive', 'term_id' => $term]);
        self::assertSame($term, $this->signer->verify($token, 101, 'w1', str_repeat('a', 64))['facts']['term_id'], 'Valid while the term exists.');
        wp_delete_term($term, 'category');

        $this->expectException(ValidationException::class);
        $this->signer->verify($token, 101, 'w1', str_repeat('a', 64));
    }

    public function testAChangedCurrentPostFailsSignatureVerification(): void
    {
        $token = $this->signer->sign(101, 'w1', str_repeat('a', 64), 'theme_builder', ['location' => 'single', 'post_id' => $this->post()]);
        $forged = $this->tamperFacts($token, ['location' => 'single', 'post_id' => $this->post()]);

        $this->expectException(ValidationException::class);
        $this->signer->verify($forged, 101, 'w1', str_repeat('a', 64));
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

    private function post(): int
    {
        $id = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Signer fact'], true);
        self::assertIsInt($id);
        $this->posts[] = $id;

        return $id;
    }

    private function term(): int
    {
        $term = wp_insert_term('Signer fact ' . bin2hex(random_bytes(4)), 'category');
        self::assertIsArray($term);
        $this->terms[] = (int) $term['term_id'];

        return (int) $term['term_id'];
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
