<?php

/** Pure registration invariants; actual REST/AJAX responses live in integration. */
class TransportRouteProbe extends \Elemacy\Core\Route
{
    protected static array $routes = [];
    protected static array $group_stack = [];
    protected static string $namespace_name = 'fixture';

    public function contract(): array
    {
        return [$this->namespace, $this->endpoint, $this->middleware, $this->access];
    }
}

check('Nested route groups preserve all prefixes, middleware and parent policies; namespace is frozen', static function () {
    TransportRouteProbe::group(['prefix' => '/outer', 'middleware' => 'Outer', 'access' => 'manage_options'], static function () {
        TransportRouteProbe::group(['prefix' => '/inner', 'middleware' => 'Inner', 'access' => 'public'], static function () {
            TransportRouteProbe::get('/leaf', ['Controller', 'index']);
        });
    });
    TransportRouteProbe::set_namespace('later');
    return TransportRouteProbe::get_routes()[0]->contract() === ['fixture', '/outer/inner/leaf', ['Outer', 'Inner'], ['manage_options', 'public']];
});

check('Throwing route groups restore the registration stack', static function () {
    try {
        TransportRouteProbe::group(['prefix' => '/failed', 'access' => 'public'], static function () {
            throw new \Error('fixture');
        });
    } catch (\Error $error) {
        // Following registration must not inherit failed group state.
    }
    $route = TransportRouteProbe::get('/after', ['Controller', 'index']);
    return $route->contract() === ['later', '/after', [], []];
});

check('REST capability declaration rejects an empty capability', static function () {
    try {
        TransportRouteProbe::get('/bad', ['Controller', 'index'])->require_capability('');
    } catch (\InvalidArgumentException $error) {
        return true;
    }
    return false;
});

class TransportAjaxProbe extends \Elemacy\Core\AjaxRouter
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
