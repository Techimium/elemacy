<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

use Closure;
use Exception;
use ReflectionMethod;
use ReflectionNamedType;
use WP_Error;
use Elemacy\Core\Http\Request;
use Elemacy\Core\Http\Response;
use Elemacy\Core\Exceptions\AuthorizationException;
use Elemacy\Core\Exceptions\InvalidRouteActionException;

/**
 * Laravel-style facade over register_rest_route for the `elemacy` REST API.
 * Controllers are resolved through the Container and a controller method's first
 * typed parameter is hydrated from the request. A route with no ->middleware()
 * is public by design — the framework does not fail closed.
 */
class Route
{
    protected static string $namespace_name = '';

    /** @var Route[] */
    protected static array $routes = [];

    protected static array $group_stack = [];

    protected string $method = '';
    protected string $endpoint = '';

    /** @var array{0: class-string, 1: string} */
    protected array $action = [];
    protected array $middleware = [];
    protected array $patterns = [];

    protected Container $container;

    /**
     * Resolves the shared Container used to auto-wire controllers.
     */
    public function __construct()
    {
        $this->container = Container::instance();
    }

    /**
     * Sets the REST namespace shared by every route registered afterward.
     *
     * @param string $namespace_name The REST namespace, e.g. `elemacy`.
     * @return void
     */
    public static function set_namespace(string $namespace_name): void
    {
        static::$namespace_name = $namespace_name;
    }

    /**
     * Attaches one or more middleware classes to this route.
     *
     * @param string|string[] $middleware Fully-qualified middleware class name(s).
     * @return $this
     */
    public function middleware($middleware)
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);

        return $this;
    }

    /**
     * Constrains a `{param}` in the endpoint to a regex pattern.
     *
     * @param string $name  The route parameter name.
     * @param string $regex The regex pattern the parameter must match.
     * @return $this
     */
    public function where(string $name, string $regex)
    {
        $this->patterns[$name] = $regex;

        return $this;
    }

    /**
     * Registers a GET route.
     *
     * @param string $endpoint The route endpoint, e.g. `/popups/{id}`.
     * @param array  $action   The controller and method, e.g. `[PopupController::class, 'show']`.
     * @return self
     */
    public static function get(string $endpoint, array $action): self
    {
        return static::register_route('get', $endpoint, $action);
    }

    /**
     * Registers a POST route.
     *
     * @param string $endpoint The route endpoint.
     * @param array  $action   The controller and method, e.g. `[PopupController::class, 'store']`.
     * @return self
     */
    public static function post(string $endpoint, array $action): self
    {
        return static::register_route('post', $endpoint, $action);
    }

    /**
     * Registers a PUT route.
     *
     * @param string $endpoint The route endpoint.
     * @param array  $action   The controller and method, e.g. `[PopupController::class, 'update']`.
     * @return self
     */
    public static function put(string $endpoint, array $action): self
    {
        return static::register_route('put', $endpoint, $action);
    }

    /**
     * Registers a PATCH route.
     *
     * @param string $endpoint The route endpoint.
     * @param array  $action   The controller and method to handle the route.
     * @return self
     */
    public static function patch(string $endpoint, array $action): self
    {
        return static::register_route('patch', $endpoint, $action);
    }

    /**
     * Registers a DELETE route.
     *
     * @param string $endpoint The route endpoint.
     * @param array  $action   The controller and method, e.g. `[PopupController::class, 'destroy']`.
     * @return self
     */
    public static function delete(string $endpoint, array $action): self
    {
        return static::register_route('delete', $endpoint, $action);
    }

    /**
     * Shared factory behind the public HTTP-verb methods above.
     *
     * @param string $method   The HTTP method, lowercase (e.g. `get`).
     * @param string $endpoint The route endpoint.
     * @param array  $action   The controller and method to handle the route.
     * @return self
     */
    protected static function register_route(string $method, string $endpoint, array $action): self
    {
        $route = new static();
        $route->method = $method;
        $route->endpoint = $endpoint;
        $route->action = $action;
        $route->apply_group_options();

        static::$routes[] = $route;

        return $route;
    }

    /**
     * Registers a group of routes sharing a prefix and/or middleware.
     *
     * @param array   $options Group options: `prefix` (string) and/or `middleware` (string|string[]).
     * @param Closure $closure Defines the routes that belong to the group.
     * @return void
     */
    public static function group(array $options, Closure $closure): void
    {
        static::$group_stack[] = $options;

        $closure();

        array_pop(static::$group_stack);
    }

    /**
     * Returns every route registered so far.
     *
     * @return Route[]
     */
    public static function get_routes(): array
    {
        return static::$routes;
    }

    /**
     * Applies the innermost active group's prefix and middleware to this route.
     *
     * @return void
     */
    protected function apply_group_options(): void
    {
        if (empty(static::$group_stack)) {
            return;
        }

        $group = end(static::$group_stack);

        if (!empty($group['prefix'])) {
            $this->endpoint = rtrim($group['prefix'], '/') . '/' . ltrim($this->endpoint, '/');
        }

        if (!empty($group['middleware'])) {
            $this->middleware($group['middleware']);
        }
    }

    /**
     * Registers this route with WordPress.
     *
     * @return void
     */
    public function register(): void
    {
        register_rest_route(static::$namespace_name, $this->get_formatted_endpoint(), [
            'methods' => strtoupper($this->method),
            'callback' => $this->resolve_route(),
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    /**
     * Runs the middleware pipeline. An AuthorizationException becomes a 403;
     * anything unexpected returns a generic message — never the raw exception
     * text, which can leak internals.
     *
     * @param \WP_REST_Request $rest_request The incoming REST request.
     * @return bool|WP_Error True to allow the request, or a WP_Error to deny it.
     */
    public function check_permission($rest_request)
    {
        $request = Request::from_wp_rest_request($rest_request);

        try {
            $pipeline = array_reduce(
                array_reverse($this->middleware),
                function ($next, $middleware) {
                    return function ($request) use ($next, $middleware) {
                        return (new $middleware())->handle($request, $next);
                    };
                },
                function ($request) {
                    return true;
                }
            );

            return $pipeline($request);
        } catch (AuthorizationException $exception) {
            return new WP_Error('rest_forbidden', $exception->getMessage(), ['status' => Response::FORBIDDEN]);
        } catch (Exception $exception) {
            return new WP_Error('rest_error', esc_html__('Something went wrong. Please try again.', 'elemacy'), ['status' => Response::INTERNAL_SERVER_ERROR]);
        }
    }

    /**
     * Converts `{param}` placeholders into the regex syntax register_rest_route() expects.
     *
     * @return string The formatted endpoint pattern.
     */
    protected function get_formatted_endpoint(): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function ($matches) {
            $param = $matches[1];
            $pattern = isset($this->patterns[$param]) ? $this->patterns[$param] : '[^/]+';

            return '(?P<' . $param . '>' . $pattern . ')';
        }, $this->endpoint);
    }

    /**
     * Builds the controller method's arguments: the first typed parameter is
     * hydrated from the request (a typed Request subclass validates), the rest
     * are resolved from the container or the raw request params.
     *
     * @param object            $controller   The resolved controller instance.
     * @param string            $method       The controller method being called.
     * @param \WP_REST_Request  $rest_request The incoming REST request.
     * @return mixed[] The arguments to pass to the controller method, in order.
     * @throws Exception If a parameter has a built-in type with no request param to match it.
     */
    protected function resolve_dependencies($controller, string $method, $rest_request): array
    {
        $reflector = new ReflectionMethod($controller, $method);
        $parameters = $reflector->getParameters();

        if (count($parameters) === 0) {
            return [];
        }

        $type = array_shift($parameters)->getType();
        $request_class = ($type instanceof ReflectionNamedType) ? $type->getName() : Request::class;

        $dependencies = [];

        if ($request_class === Request::class || is_subclass_of($request_class, Request::class)) {
            $request = $request_class::from_wp_rest_request($rest_request);
            $request->clean();
            $dependencies[] = $request;
        }

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if (!is_null($type) && $type->isBuiltin()) {
                /* translators: %s: Parameter name */
                throw new Exception(sprintf(esc_html__('Parameter %s must not be a built-in type.', 'elemacy'), esc_html($parameter->getName())));
            }

            $dependencies[] = $type
                ? $this->container->make($type->getName())
                : ($rest_request->get_param($parameter->getName()) ?? null);
        }

        return $dependencies;
    }

    /**
     * Builds the REST callback: resolves the controller, hydrates its
     * arguments, and turns any thrown exception into a JSON response.
     *
     * @return Closure The `register_rest_route()` callback.
     */
    protected function resolve_route(): Closure
    {
        return function ($rest_request) {
            if (!is_array($this->action) || count($this->action) !== 2) {
                /* translators: %s: Route endpoint */
                throw new InvalidRouteActionException(sprintf(esc_html__('Invalid controller action for the route %s', 'elemacy'), esc_html($this->endpoint)));
            }

            list($controller, $method) = $this->action;

            if (!class_exists($controller)) {
                /* translators: %s: Controller class */
                throw new InvalidRouteActionException(sprintf(esc_html__('Controller %s not found', 'elemacy'), esc_html($controller)));
            }

            $controller_instance = $this->container->make($controller);

            if (!method_exists($controller_instance, $method)) {
                /* translators: 1: Method name, 2: Controller class */
                throw new InvalidRouteActionException(sprintf(esc_html__('The method %1$s is missing in the controller %2$s', 'elemacy'), esc_html($method), esc_html($controller)));
            }

            try {
                $dependencies = $this->resolve_dependencies($controller_instance, $method, $rest_request);

                return $controller_instance->$method(...$dependencies);
            } catch (Exception $exception) {
                return ApiExceptionHandler::get_response($exception);
            }
        };
    }
}
