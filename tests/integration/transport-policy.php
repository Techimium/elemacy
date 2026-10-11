<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Http\Route;
use Elemacy\Http\Request;
use Elemacy\Http\Middlewares\AdminMiddleware;

require_once __DIR__ . '/IntegrationTestCase.php';

class TransportObservedController
{
    public static int $constructed = 0;
    public static int $executed = 0;

    public function __construct()
    {
        ++self::$constructed;
    }

    public function index()
    {
        ++self::$executed;
        return ['accepted' => true];
    }
}

class TransportBrokenController
{
    public function __construct()
    {
        throw new \Error('internal-secret-construction');
    }

    public function index()
    {
        return [];
    }
}

class TransportOuterMiddleware
{
    public static array $calls = [];

    public function handle(Request $request, callable $next)
    {
        self::$calls[] = 'outer';
        return $next($request);
    }
}

class TransportInnerMiddleware
{
    public function handle(Request $request, callable $next)
    {
        TransportOuterMiddleware::$calls[] = 'inner';
        return $next($request);
    }
}

final class TransportPolicyTest extends IntegrationTestCase
{
    private array $snapshot = [];
    private array $restSnapshot = [];

    protected function setUp(): void
    {
        parent::setUp();
        $server = rest_get_server();
        foreach (['endpoints', 'namespaces', 'route_options'] as $name) {
            $property = new \ReflectionProperty($server, $name);
            $property->setAccessible(true);
            $this->restSnapshot[$name] = $property->getValue($server);
        }
        foreach (['routes', 'namespace_name', 'group_stack'] as $name) {
            $property = new \ReflectionProperty(Route::class, $name);
            $property->setAccessible(true);
            $this->snapshot[$name] = $property->getValue();
            $property->setValue(null, $name === 'namespace_name' ? 'elemacy-policy-fixture/v1' : []);
        }
        TransportObservedController::$constructed = TransportObservedController::$executed = 0;
        TransportOuterMiddleware::$calls = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->snapshot as $name => $value) {
            $property = new \ReflectionProperty(Route::class, $name);
            $property->setAccessible(true);
            $property->setValue(null, $value);
        }
        foreach ($this->restSnapshot as $name => $value) {
            $property = new \ReflectionProperty(rest_get_server(), $name);
            $property->setAccessible(true);
            $property->setValue(rest_get_server(), $value);
        }
        parent::tearDown();
    }

    public function testRouteWithoutMiddlewareIsPublic(): void
    {
        wp_set_current_user(0);
        Route::get('/open', [TransportObservedController::class, 'index'])->register();
        self::assertSame(200, rest_do_request('/elemacy-policy-fixture/v1/open')->get_status());
        self::assertSame(1, TransportObservedController::$executed);
    }

    public function testGuestCannotUseAnAdminRouteEvenWithNonce(): void
    {
        wp_set_current_user(0);
        Route::get('/admin', [TransportObservedController::class, 'index'])->middleware(AdminMiddleware::class)->register();
        $request = new \WP_REST_Request('GET', '/elemacy-policy-fixture/v1/admin');
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        self::assertSame(403, rest_do_request($request)->get_status());
        self::assertSame(0, TransportObservedController::$constructed);
    }

    public function testNonAdminCannotUseAnAdminRoute(): void
    {
        $subscriber = wp_insert_user([
            'user_login' => 'elemacy_transport_subscriber', 'user_pass' => wp_generate_password(32),
            'user_email' => 'elemacy_transport_subscriber@example.invalid', 'role' => 'subscriber',
        ]);
        self::assertIsInt($subscriber);
        try {
            wp_set_current_user($subscriber);
            Route::get('/admin', [TransportObservedController::class, 'index'])->middleware(AdminMiddleware::class)->register();
            $response = rest_do_request('/elemacy-policy-fixture/v1/admin');
            self::assertSame(403, $response->get_status());
            self::assertSame('rest_forbidden', $response->get_data()['code']);
            self::assertSame(0, TransportObservedController::$constructed);
        } finally {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($subscriber);
        }
    }

    public function testNestedGroupsComposeMiddlewareAndFreezeNamespace(): void
    {
        Route::group(['prefix' => '/outer', 'middleware' => TransportOuterMiddleware::class], static function (): void {
            Route::group(['prefix' => '/inner', 'middleware' => TransportInnerMiddleware::class], static function (): void {
                Route::get('/observed', [TransportObservedController::class, 'index']);
            });
        });
        Route::set_namespace('unrelated-extension/v1');
        Route::get_routes()[0]->register();
        self::assertSame(200, rest_do_request('/elemacy-policy-fixture/v1/outer/inner/observed')->get_status());
        self::assertSame(['outer', 'inner'], TransportOuterMiddleware::$calls);
        self::assertSame(1, TransportObservedController::$executed);
    }

    public function testHandlerConstructionThrowableIsAClientSafeResponse(): void
    {
        Route::get('/broken', [TransportBrokenController::class, 'index'])->register();
        $response = rest_do_request('/elemacy-policy-fixture/v1/broken');
        self::assertSame(500, $response->get_status());
        self::assertStringNotContainsString('internal-secret', wp_json_encode($response->get_data()));
    }
    /** @dataProvider ajaxCases */
    public function testActualAjaxPolicyBoundary(string $case, bool $success): void
    {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/ajax-policy-request.php') . ' ' . escapeshellarg($case);
        $output = [];
        exec($command, $output, $status);
        self::assertSame(0, $status);
        $json = json_decode(implode("\n", $output), true);
        self::assertIsArray($json);
        self::assertSame($success, $json['success']);
        self::assertStringNotContainsString('secret-constructor', implode("\n", $output));
        if ($case === 'good') {
            self::assertSame(['constructed' => 1, 'executed' => 1], $json['data']);
        }
        if ($case === 'omitted') {
            self::assertSame(['registered' => false, 'constructed' => 0], $json['data']);
        }
    }

    public static function ajaxCases(): array
    {
        return [['good', true], ['omitted', true], ['admin', false], ['broken', false], ['bad-nonce', false]];
    }

}
