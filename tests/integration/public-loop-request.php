<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Core\Exceptions\ValidationException;
use Elemacy\Modules\Widgets\DTO\PublicLoopRequest;
use PHPUnit\Framework\TestCase;

final class PublicLoopRequestTest extends TestCase
{
    private static function claims(): array
    {
        return ['version' => 1, 'owner_id' => 123, 'widget_id' => 'eac2002', 'page' => 1,
            'fingerprint' => str_repeat('a', 64)];
    }

    public function testAcceptedClaimsKeepExactIdentityAndBoundaryValues(): void
    {
        $claims = self::claims();
        $claims['page'] = 10000;
        $claims['context'] = str_repeat('a', 8127) . '.' . str_repeat('b', 64);
        $request = PublicLoopRequest::from_json(json_encode($claims));
        self::assertSame(123, $request->owner_id());
        self::assertSame('eac2002', $request->widget_id());
        self::assertSame(10000, $request->page());
        self::assertSame(str_repeat('a', 64), $request->fingerprint());
        self::assertSame(8192, strlen($request->context()));
        self::assertSame('', PublicLoopRequest::from_json(json_encode(self::claims()))->context());
    }

    /** @dataProvider malformedRequests */
    public function testMalformedClaimsAreRejectedWithoutDatabaseAccess($raw, string $code): void
    {
        global $wpdb;
        $before = $wpdb->num_queries;
        $exception = null;
        try {
            PublicLoopRequest::from_json($raw);
        } catch (ValidationException $error) {
            $exception = $error;
        }
        self::assertInstanceOf(ValidationException::class, $exception);
        self::assertSame($code, $exception->get_errors()['code']);
        self::assertSame($before, $wpdb->num_queries, 'Input parsing must not query the database.');
    }

    public static function malformedRequests(): array
    {
        $invalid = PublicLoopRequest::INVALID_REQUEST;
        $refresh = PublicLoopRequest::REFRESH_REQUIRED;
        $cases = [
            'missing new protocol' => [null, $refresh],
            'empty new protocol' => ['', $refresh],
            'legacy settings object' => ['{"template_id":42}', $refresh],
            'native array' => [[], $invalid],
            'native integer' => [1, $invalid],
            'malformed JSON' => ['{"version":', $invalid],
            'JSON scalar' => ['true', $invalid],
            'JSON list' => ['[1,2,3]', $invalid],
            'invalid UTF-8' => ["{\"x\":\"\xff\"}", $invalid],
            'oversized outer JSON before decoding' => [str_repeat(' ', PublicLoopRequest::MAX_REQUEST_BYTES + 1), $invalid],
            'deep JSON' => ['{"version":1,"context":{"a":{"b":{"c":{"d":1}}}}}', $invalid],
        ];
        foreach ([0, -1, 10001, PHP_INT_MAX, '1', '99999999999999999999999999999', 1.5, true, null, []] as $i => $page) {
            $claims = self::claims();
            $claims['page'] = $page;
            $cases['invalid page ' . $i] = [json_encode($claims), $invalid];
        }
        foreach ([0, -1, '123', 1.5, true, null, []] as $i => $owner) {
            $claims = self::claims();
            $claims['owner_id'] = $owner;
            $cases['invalid owner ' . $i] = [json_encode($claims), $invalid];
        }
        foreach (['version' => '1', 'widget_id' => [], 'fingerprint' => 123, 'context' => null] as $field => $value) {
            $claims = self::claims();
            $claims[$field] = $value;
            $cases['wrong shape ' . $field] = [json_encode($claims), $invalid];
        }
        foreach (['', 'has space', str_repeat('a', 65)] as $i => $widget) {
            $claims = self::claims();
            $claims['widget_id'] = $widget;
            $cases['invalid widget ' . $i] = [json_encode($claims), $invalid];
        }
        foreach (['context' => str_repeat('a', 8128) . '.' . str_repeat('b', 64),
            'fingerprint' => str_repeat('a', 63), 'settings' => ['post_type' => 'private']] as $field => $value) {
            $claims = self::claims();
            $claims[$field] = $value;
            $cases['invalid bound or extra field ' . $field] = [json_encode($claims), $invalid];
        }
        $cases['context cannot contain unsigned claims'] = [json_encode(self::claims() + ['context' => '{"post_id":1}']), $invalid];
        $claims = self::claims();
        $claims['version'] = 2;
        $cases['unsupported version requires refresh'] = [json_encode($claims), $refresh];
        $cases['JSON integer overflow'] = [str_replace('"page":1', '"page":99999999999999999999999999', json_encode(self::claims())), $invalid];
        return $cases;
    }
}
