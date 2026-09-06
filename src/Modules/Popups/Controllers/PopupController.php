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
        $result = $this->service->get_all($filter_dto);

        return Response::create()->json([
            'data' => [
                'results' => PopupListResource::collection($result->items),
                'pagination' => $result->pagination(),
            ],
            'message' => __('Popups retrieved successfully', 'elemacy')
        ]);
    }

    public function show(Request $request, $id)
    {
        $popup = $this->service->get_or_fail((int) $id);

        return Response::create()->json([
            'data' => PopupResource::make($popup),
            'message' => __('Popup retrieved successfully', 'elemacy')
        ]);
    }

    public function store(CreatePopupRequest $request)
    {
        $popup = $this->service->create(CreatePopupDTO::from_array($request->clean()));

        return Response::create()->json([
            'message' => __('Popup created successfully', 'elemacy'),
            'data' => PopupResource::make($popup)
        ], Response::CREATED);
    }

    public function update(UpdatePopupRequest $request, $id)
    {
        $popup = $this->service->update((int) $id, UpdatePopupDTO::from_array($request->clean()));

        return Response::create()->json([
            'message' => __('Popup updated successfully', 'elemacy'),
            'data' => PopupResource::make($popup)
        ]);
    }

    public function duplicate(Request $request, $id)
    {
        $popup = $this->service->duplicate((int) $id);

        return Response::create()->json([
            'message' => __('Popup duplicated successfully', 'elemacy'),
            'data' => PopupResource::make($popup)
        ], Response::CREATED);
    }

    public function destroy(Request $request, $id)
    {
        $this->service->delete((int) $id);

        return Response::create()->json([
            'message' => __('Popup deleted successfully', 'elemacy')
        ]);
    }
}
