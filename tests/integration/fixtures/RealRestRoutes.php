<?php

namespace Elemacy\Tests\Integration\Fixtures;

use Elemacy\Http\Route;

/**
 * Makes sure the plugins' real REST routes (namespace `elemacy/v1`) are on the
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

        $namespace = self::elemacy_rest_namespace();

        if (in_array($namespace, $server->get_namespaces(), true)) {
            return;
        }

        foreach (Route::get_routes() as $route) {
            $property = new \ReflectionProperty(Route::class, 'namespace');
            $property->setAccessible(true);

            if ($namespace === $property->getValue($route)) {
                $route->register();
            }
        }

        self::assertContains($namespace, $server->get_namespaces(), 'Fixture: the real routes are registered.');
    }

    /** The REST namespace the plugins register their routes under (Route::set_namespace()). */
    private static function elemacy_rest_namespace(): string
    {
        $property = new \ReflectionProperty(Route::class, 'namespace_name');
        $property->setAccessible(true);

        return (string) $property->getValue();
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
