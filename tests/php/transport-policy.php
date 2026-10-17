<?php

/** Pure registration invariants; actual REST/AJAX responses live in integration. */
class TransportRouteProbe extends \Elemacy\Http\Route
{
    protected static array $routes = [];
    protected static array $group_stack = [];
    protected static string $namespace_name = 'fixture';

    public function contract(): array
    {
        return [$this->namespace, $this->endpoint, $this->middleware];
    }
}

check('Nested route groups preserve all prefixes and middleware; namespace is frozen', static function () {
    TransportRouteProbe::group(['prefix' => '/outer', 'middleware' => 'Outer'], static function () {
        TransportRouteProbe::group(['prefix' => '/inner', 'middleware' => 'Inner'], static function () {
            TransportRouteProbe::get('/leaf', ['Controller', 'index']);
        });
    });
    TransportRouteProbe::set_namespace('later');
    return TransportRouteProbe::get_routes()[0]->contract() === ['fixture', '/outer/inner/leaf', ['Outer', 'Inner']];
});

check('Throwing route groups restore the registration stack', static function () {
    try {
        TransportRouteProbe::group(['prefix' => '/failed', 'middleware' => 'Failed'], static function () {
            throw new \Error('fixture');
        });
    } catch (\Error $error) {
        // Following registration must not inherit failed group state.
    }
    $route = TransportRouteProbe::get('/after', ['Controller', 'index']);
    return $route->contract() === ['later', '/after', []];
});

class TransportAjaxProbe extends \Elemacy\Http\AjaxRouter
{
    protected static $routes = [];
}
class TransportNeverConstructedController
{
    public function __construct()
    {
        throw new \Error('Registration eagerly constructed an unrelated handler.');
    }
    public function index()
    {
    }
}

check('AJAX registration is lazy even for a controller whose constructor throws', static function () {
    TransportAjaxProbe::add_action('elemacy_transport_lazy_fixture', [TransportNeverConstructedController::class, 'index'])->for_public();
    TransportAjaxProbe::register();
    return true;
});
