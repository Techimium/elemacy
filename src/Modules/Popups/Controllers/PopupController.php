<?php

namespace Elemacy\Modules\Popups\Controllers;

defined('ABSPATH') || exit;

use Elemacy\Core\Http\Request;
use Elemacy\Core\Http\Response;
use Elemacy\Modules\Popups\DTO\CreatePopupDTO;
use Elemacy\Modules\Popups\DTO\PopupListFilterDTO;
use Elemacy\Modules\Popups\DTO\UpdatePopupDTO;
use Elemacy\Modules\Popups\Requests\CreatePopupRequest;
use Elemacy\Modules\Popups\Requests\UpdatePopupRequest;
use Elemacy\Modules\Popups\Resources\PopupListResource;
use Elemacy\Modules\Popups\Resources\PopupResource;
use Elemacy\Modules\Popups\Services\PopupService;

class PopupController
{
    protected $service;

    public function __construct()
    {
        $this->service = new PopupService();
    }

    public function index(Request $request)
    {
        $filter_dto = PopupListFilterDTO::from_array($request->all());
        $popups = $this->service->get_all($filter_dto);

        return Response::create()->json([
            'data' => PopupListResource::collection($popups),
            'message' => __('Popups retrieved successfully', 'elemacy')
        ]);
    }

    public function show(Request $request, $id)
    {
        $popup = $this->service->get((int) $id);

        if (!$popup) {
            return Response::create()->json([
                'message' => 'Popup not found'
            ], Response::NOT_FOUND);
        }

        return Response::create()->json([
            'data' => PopupResource::make($popup),
            'message' => __('Popup retrieved successfully', 'elemacy')
        ]);
    }

    public function store(CreatePopupRequest $request)
    {
        $data = $request->clean();
        $result = $this->service->create(CreatePopupDTO::from_array($data));

        if (is_wp_error($result)) {
            return Response::create()->json([
                'message' => $result->get_error_message()
            ], Response::INTERNAL_SERVER_ERROR);
        }

        return Response::create()->json([
            'message' => __('Popup created successfully', 'elemacy'),
            'data' => PopupResource::make($result)
        ], Response::CREATED);
    }

    public function update(UpdatePopupRequest $request, $id)
    {
        $data = $request->clean();
        $result = $this->service->update((int) $id, UpdatePopupDTO::from_array($data));

        if (is_wp_error($result)) {
            $status = $result->get_error_code() === 'not_found' ? Response::NOT_FOUND : Response::INTERNAL_SERVER_ERROR;
            return Response::create()->json([
                'message' => $result->get_error_message()
            ], $status);
        }

        return Response::create()->json([
            'message' => __('Popup updated successfully', 'elemacy'),
            'data' => PopupResource::make($result)
        ]);
    }

    public function duplicate(Request $request, $id)
    {
        $result = $this->service->duplicate((int) $id);

        if (is_wp_error($result)) {
            $status = $result->get_error_code() === 'not_found' ? Response::NOT_FOUND : Response::INTERNAL_SERVER_ERROR;
            return Response::create()->json([
                'message' => $result->get_error_message()
            ], $status);
        }

        return Response::create()->json([
            'message' => __('Popup duplicated successfully', 'elemacy'),
            'data' => PopupResource::make($result)
        ], Response::CREATED);
    }

    public function destroy(Request $request, $id)
    {
        $result = $this->service->delete((int) $id);

        if (is_wp_error($result)) {
            $status = $result->get_error_code() === 'not_found' ? Response::NOT_FOUND : Response::INTERNAL_SERVER_ERROR;
            return Response::create()->json([
                'message' => $result->get_error_message()
            ], $status);
        }

        return Response::create()->json([
            'message' => __('Popup deleted successfully', 'elemacy')
        ]);
    }
}
