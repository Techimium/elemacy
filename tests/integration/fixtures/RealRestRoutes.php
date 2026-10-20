<?php

namespace Elemacy\Tests\Integration\Fixtures;

use Elemacy\Http\Route;

/**
 * Makes sure the plugins' real REST routes (namespace `elemacy`) are on the
 * shared REST server, whatever an earlier test class did to it, and puts the
 * server back as it was afterwards.
 */
trait RealRestRoutes
{
    private array $rest_server_snapshot = [];

    private function boot_real_routes(): void
    {
        $server = rest_get_server();

        foreach (['endpoints', 'namespaces', 'route_options'] as $name) {
            $property = new \ReflectionProperty($server, $name);
            $property->setAccessible(true);
            $this->rest_server_snapshot[$name] = $property->getValue($server);
        }

        if (in_array('elemacy', $server->get_namespaces(), true)) {
            return;
        }

        foreach (Route::get_routes() as $route) {
            $namespace = new \ReflectionProperty(Route::class, 'namespace');
            $namespace->setAccessible(true);

            if ('elemacy' === $namespace->getValue($route)) {
                $route->register();
            }
        }

        self::assertContains('elemacy', $server->get_namespaces(), 'Fixture: the real routes are registered.');
    }

    private function restore_rest_server(): void
    {
        $server = rest_get_server();

        foreach ($this->rest_server_snapshot as $name => $value) {
            $property = new \ReflectionProperty($server, $name);
            $property->setAccessible(true);
            $property->setValue($server, $value);
        }
    }
}
