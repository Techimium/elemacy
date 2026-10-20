<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Http\Middlewares\AdminMiddleware;
use Elemacy\Http\Route;
use Elemacy\Tests\Integration\Fixtures\RealRestRoutes;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/RealRestRoutes.php';

/**
 * Every REST route either of the plugins registers is admin-only, unless it is
 * on the short public list below with the reason it may be. A new route that
 * forgets its middleware fails here, not in review.
 *
 * (Public form submissions and the public loop endpoints are admin-ajax
 * handlers with their own nonce and rate policy, not Route routes, so they are
 * not in this list.)
 */
final class RouteAuthorizationTest extends IntegrationTestCase
{
    use RealRestRoutes;

    /** "METHOD /endpoint" => why anyone may call it. */
    private const PUBLIC_ROUTES = [
        'POST /popups/{id}/track' => 'Visitors\' browsers send impression and conversion beacons; the request is size-bound and rate-limited, and only a deliverable popup can be counted.',
    ];

    private array $route_snapshot = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->boot_real_routes();
        $property = new \ReflectionProperty(Route::class, 'routes');
        $property->setAccessible(true);
        $this->route_snapshot = $property->getValue();
    }

    protected function tearDown(): void
    {
        $property = new \ReflectionProperty(Route::class, 'routes');
        $property->setAccessible(true);
        $property->setValue(null, $this->route_snapshot);
        $this->restore_rest_server();
        parent::tearDown();
    }

    /**
     * @return array<int, array{key: string, method: string, endpoint: string, middleware: string[]}>
     */
    private function routes(): array
    {
        $routes = [];

        foreach (Route::get_routes() as $route) {
            $read = static function (string $name) use ($route) {
                $property = new \ReflectionProperty(Route::class, $name);
                $property->setAccessible(true);

                return $property->getValue($route);
            };
            $method = strtoupper((string) $read('method'));
            $endpoint = (string) $read('endpoint');

            $routes[] = [
                'key' => $method . ' ' . $endpoint,
                'method' => $method,
                'endpoint' => $endpoint,
                'middleware' => (array) $read('middleware'),
            ];
        }

        return $routes;
    }

    /** @return string[] The routes that are neither admin-only nor on the public list. */
    private function unguarded_routes(): array
    {
        $unguarded = [];

        foreach ($this->routes() as $route) {
            if (!in_array(AdminMiddleware::class, $route['middleware'], true) && !isset(self::PUBLIC_ROUTES[$route['key']])) {
                $unguarded[] = $route['key'];
            }
        }

        return $unguarded;
    }

    public function testEveryRouteIsVersionedUnderElemacyV1(): void
    {
        $index = rest_do_request(new \WP_REST_Request('GET', '/'))->get_data();
        $namespaces = (array) ($index['namespaces'] ?? []);
        self::assertContains('elemacy/v1', $namespaces, 'The REST index lists elemacy/v1: ' . wp_json_encode($namespaces));
        self::assertNotContains('elemacy', $namespaces, 'No unversioned elemacy namespace.');

        $elemacy_routes = array_values(array_filter(array_keys((array) ($index['routes'] ?? [])), static fn(string $route): bool => 0 === strpos($route, '/elemacy')));
        self::assertNotEmpty($elemacy_routes, 'The index lists the Elemacy routes.');
        foreach ($elemacy_routes as $route) {
            self::assertStringStartsWith('/elemacy/v1/', $route === '/elemacy/v1' ? '/elemacy/v1/' : $route, 'Every Elemacy route is versioned.');
        }
    }

    public function testEveryRouteIsAdminOnlyOrOnTheExplicitPublicList(): void
    {
        self::assertNotEmpty($this->routes(), 'The sweep must see the registered routes.');
        self::assertSame([], $this->unguarded_routes(), 'These routes have no AdminMiddleware and are not on the public list.');
    }

    public function testThePublicListHasNoStaleEntries(): void
    {
        $keys = array_column($this->routes(), 'key');

        foreach (array_keys(self::PUBLIC_ROUTES) as $public) {
            if ('POST /popups/{id}/track' === $public && !class_exists(\ElemacyPro\Extensions\Popups\Services\AnalyticsService::class)) {
                continue; // Pro's route exists only with the declared Pro pairing.
            }
            self::assertContains($public, $keys, "$public is listed as public but is not a registered route any more.");
        }
    }

    public function testASweepDetectsARouteRegisteredWithoutMiddleware(): void
    {
        Route::get('/scratch/unguarded', [self::class, 'testASweepDetectsARouteRegisteredWithoutMiddleware']);
        Route::post('/scratch/guarded', [self::class, 'testASweepDetectsARouteRegisteredWithoutMiddleware'])->middleware(AdminMiddleware::class);

        self::assertSame(['GET /scratch/unguarded'], $this->unguarded_routes());
    }

    public function testEveryAdminRouteRefusesAnEditorAndAVisitor(): void
    {
        $editor = wp_insert_user([
            'user_login' => 'elemacy_route_editor', 'user_pass' => wp_generate_password(32),
            'user_email' => 'elemacy_route_editor@example.invalid', 'role' => 'editor',
        ]);
        self::assertIsInt($editor);
        $dispatched = 0;

        try {
            foreach ($this->routes() as $route) {
                if (isset(self::PUBLIC_ROUTES[$route['key']])) {
                    continue;
                }

                $url = '/elemacy/v1' . str_replace(['{id}', '{name}'], ['1', 'x'], $route['endpoint']);

                foreach (['an editor' => $editor, 'a visitor' => 0] as $who => $user) {
                    wp_set_current_user($user);
                    $response = rest_do_request(new \WP_REST_Request($route['method'], $url));

                    self::assertContains($response->get_status(), [401, 403], "{$route['key']} must refuse $who, got {$response->get_status()}.");
                    ++$dispatched;
                }
            }
        } finally {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($editor);
        }

        self::assertGreaterThan(30, $dispatched, 'The sweep must have dispatched every admin route as both users.');
    }
}
