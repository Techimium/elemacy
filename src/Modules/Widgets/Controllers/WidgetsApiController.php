<?php

namespace Elemacy\Modules\Widgets\Controllers;

defined('ABSPATH') || exit;

use Elemacy\Core\Http\Request;
use Elemacy\Core\Http\Response;
use Elemacy\Modules\Widgets\Resources\WidgetListResource;
use Elemacy\Modules\Widgets\Services\WidgetService;
use Exception;

class WidgetsApiController
{
    protected $service;

    public function __construct()
    {
        $this->service = new WidgetService();
    }

    public function index(Request $request)
    {
        $widgets = $this->service->get_all_widgets();

        return Response::create()->json([
            'data' => WidgetListResource::collection($widgets),
            'message' => __('Widgets retrieved successfully', 'elemacy')
        ]);
    }

    public function toggle(Request $request, $name)
    {
        $action = $request->get_string('action');

        if (!in_array($action, ['enable', 'disable'], true)) {
            throw new Exception(esc_html__('Invalid action', 'elemacy'));
        }

        $this->service->toggle_widget($name, $action);

        return Response::create()->json([
            'message' => __('Widget toggled successfully', 'elemacy')
        ]);
    }
}
