<?php

namespace Elemacy\Modules\Widgets\Controllers;

defined('ABSPATH') || exit;

use Elemacy\Core\Exceptions\HttpException;
use Elemacy\Core\Http\Request;
use Elemacy\Core\Http\Response;
use Elemacy\Modules\Widgets\Resources\WidgetListResource;
use Elemacy\Modules\Widgets\Services\WidgetService;

class WidgetsApiController
{
    protected WidgetService $service;

    public function __construct()
    {
        $this->service = new WidgetService();
    }

    /**
     * Lists every available widget and its enabled/disabled state.
     *
     * @param Request $request The incoming REST request.
     * @return Response
     */
    public function index(Request $request)
    {
        $widgets = $this->service->get_all_widgets();

        return Response::create()->json([
            'data' => WidgetListResource::collection($widgets),
            'message' => __('Widgets retrieved successfully', 'elemacy')
        ]);
    }

    /**
     * Enables or disables a widget.
     *
     * @param Request $request The incoming REST request; expects an `action` param of `enable`/`disable`.
     * @param string  $name    The widget slug to toggle.
     * @return Response
     * @throws HttpException If `action` isn't `enable`/`disable`.
     */
    public function toggle(Request $request, $name)
    {
        $action = $request->get_string('action');

        if (!in_array($action, ['enable', 'disable'], true)) {
            throw new HttpException(
                esc_html__('Invalid action', 'elemacy'),
                Response::BAD_REQUEST // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- HTTP status code, not output.
            );
        }

        $this->service->toggle_widget($name, $action);

        return Response::create()->json([
            'message' => __('Widget toggled successfully', 'elemacy')
        ]);
    }
}
