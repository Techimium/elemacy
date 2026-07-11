<?php

namespace Elemacy\Core;

use Elemacy\Core\Exceptions\HttpException;
use Elemacy\Core\Exceptions\ValidationException;
use Elemacy\Core\Http\Response;
use Elemacy\Core\Http\SiteRequest;
use Elemacy\Core\Http\SiteResponse;
use Elemacy\Support\Utils;

defined('ABSPATH') || exit;

use Exception;

class AjaxRouter
{
    /**
     * All registered ajax routes/actions.
     *
     * @var array
     */
    protected static $routes = [];

    /**
     * Ajax action.
     *
     * @var string
     */
    protected $action;

    /**
     * Controller and method to call.
     *
     * @var array
     */
    protected $callback;

    /**
     * User type for the ajax request
     *
     * @var string|null
     */
    protected $user_type = null;

    /**
     * Whether the route requires nonce check.
     *
     * @var bool
     */
    protected $needs_nonce_check = false;

    /**
     * The nonce action/name for verification.
     *
     * @var string|null
     */
    protected $nonce_action = null;

    /**
     * Bootstraps the ajax router: wires every registered route to
     * `wp_ajax_*`/`wp_ajax_nopriv_*`.
     *
     * @return void
     * @throws Exception If a route's callback is malformed or its controller/method doesn't exist.
     */
    public static function register()
    {
        foreach (static::$routes as $route) {
            if (count($route->callback) < 2) {
                throw new Exception(esc_html__('Invalid ajax callback handler', 'elemacy'));
            }

            if (!class_exists($route->callback[0])) {
                /* translators: %s: Controller name */
                throw new Exception(sprintf(esc_html__('Controller %s not found', 'elemacy'), esc_html($route->callback[0])));
            }

            if (!method_exists($route->callback[0], $route->callback[1])) {
                /* translators: %s: Method name */
                throw new Exception(sprintf(esc_html__('Method %s not found', 'elemacy'), esc_html($route->callback[1])));
            }

            $controller = Container::instance()->make($route->callback[0]);

            $callback = function () use ($controller, $route) {
                // Perform nonce verification if required
                if ($route->needs_nonce_check) {
                    $nonce = Sanitizer::apply_rule(wp_unslash($_POST['_wpnonce'] ?? $_GET['_wpnonce'] ?? ''), Sanitizer::TEXT); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    $nonce_action = $route->nonce_action ?? Utils::with_prefix('ajax_nonce');

                    if (!wp_verify_nonce($nonce, $nonce_action)) {
                        wp_send_json_error(__('Security check failed', 'elemacy'), 403);
                        return;
                    }
                }

                try {
                    return $controller->{$route->callback[1]}(static::get_request_instance());
                } catch (Exception $error) {
                    return static::send_error_response($error);
                }
            };

            if ($route->user_type === 'guest' || $route->user_type === null) {
                add_action('wp_ajax_nopriv_' . $route->action, $callback);
            }

            if ($route->user_type === 'auth' || $route->user_type === null) {
                add_action('wp_ajax_' . $route->action, $callback);
            }
        }
    }

    /**
     * Registers an ajax route/action.
     *
     * @param string $action   The `admin-ajax.php` action name.
     * @param array  $callback The controller and method, e.g. `[MyController::class, 'index']`.
     * @return static
     */
    public static function add_action($action, $callback)
    {
        $route = new static();
        $route->action = $action;
        $route->callback = $callback;

        static::$routes[] = $route;

        return $route;
    }

    /**
     * Marks this route as accessible to guests (`wp_ajax_nopriv_*`).
     *
     * @return $this
     */
    public function for_guest()
    {
        $this->user_type = 'guest';

        return $this;
    }

    /**
     * Requires a valid nonce before this route's callback runs.
     *
     * @param string|null $nonce_action The nonce action to verify against.
     *                                  Defaults to the plugin's shared ajax nonce.
     * @return $this
     */
    public function with_nonce($nonce_action = null)
    {
        $this->needs_nonce_check = true;
        $this->nonce_action = $nonce_action ?? Utils::with_prefix('ajax_nonce');

        return $this;
    }

    /**
     * Marks this route as accessible only to logged-in users (`wp_ajax_*`).
     *
     * @return $this
     */
    public function for_authenticated()
    {
        $this->user_type = 'auth';

        return $this;
    }

    /**
     * Returns the current SiteRequest instance.
     *
     * @return SiteRequest
     */
    protected static function get_request_instance()
    {
        return SiteRequest::instance();
    }

    /**
     * Converts a caught exception into a JSON error response.
     *
     * @param Exception $error The exception raised by the route's callback.
     * @return void
     */
    protected static function send_error_response(Exception $error)
    {
        if ($error instanceof ValidationException) {
            return SiteResponse::instance()->json_error([
                'message' => $error->getMessage(),
                'errors' => $error->get_errors(),
            ], $error->getCode() ? $error->getCode() : 422);
        }

        // HttpExceptions carry a client-safe, translated message; surface it.
        if ($error instanceof HttpException) {
            return SiteResponse::instance()->json_error([
                'message' => $error->getMessage(),
                'errors' => [],
            ], $error->getCode() ? $error->getCode() : Response::INTERNAL_SERVER_ERROR);
        }

        // Anything else is unexpected — log it and return a generic message
        // rather than leaking the raw exception text.
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Elemacy] ' . get_class($error) . ': ' . $error->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }

        return SiteResponse::instance()->json_error([
            'message' => esc_html__('Something went wrong. Please try again.', 'elemacy'),
            'errors' => [],
        ], Response::INTERNAL_SERVER_ERROR);
    }
}
