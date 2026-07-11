<?php

namespace Elemacy\Modules\ThemeBuilder\Controllers;

defined('ABSPATH') || exit;

use Elemacy\Core\Http\Request;
use Elemacy\Core\Http\Response;
use Elemacy\Modules\ThemeBuilder\DTO\CreateTemplateDTO;
use Elemacy\Modules\ThemeBuilder\DTO\TemplateListFilterDTO;
use Elemacy\Modules\ThemeBuilder\DTO\UpdateTemplateDTO;
use Elemacy\Modules\ThemeBuilder\Requests\CreateTemplateRequest;
use Elemacy\Modules\ThemeBuilder\Requests\UpdateTemplateRequest;
use Elemacy\Modules\ThemeBuilder\Resources\TemplateListResource;
use Elemacy\Modules\ThemeBuilder\Resources\TemplateResource;
use Elemacy\Modules\ThemeBuilder\Services\TemplateService;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

class ThemeBuilderController
{
    protected $service;

    public function __construct()
    {
        $this->service = new TemplateService();
    }

    public function types()
    {
        return Response::create()->json([
            'data' => ThemeBuilderManager::instance()->get_available_template_types(),
        ]);
    }

    public function index(Request $request)
    {
        $filter_dto = TemplateListFilterDTO::from_array($request->all());
        $result = $this->service->get_all($filter_dto);

        return Response::create()->json([
            'data' => [
                'results' => TemplateListResource::collection($result->items),
                'pagination' => $result->pagination(),
            ],
            'message' => __('Templates retrieved successfully', 'elemacy')
        ]);
    }

    public function show(Request $request, $id)
    {
        $template = $this->service->get_or_fail((int) $id);

        return Response::create()->json([
            'data' => TemplateResource::make($template),
            'message' => __('Template retrieved successfully', 'elemacy')
        ]);
    }

    public function store(CreateTemplateRequest $request)
    {
        $template = $this->service->create(CreateTemplateDTO::from_array($request->clean()));

        return Response::create()->json([
            'message' => __('Template created successfully', 'elemacy'),
            'data' => TemplateResource::make($template)
        ], Response::CREATED);
    }

    public function update(UpdateTemplateRequest $request, $id)
    {
        $template = $this->service->update((int) $id, UpdateTemplateDTO::from_array($request->clean()));

        return Response::create()->json([
            'message' => __('Template updated successfully', 'elemacy'),
            'data' => TemplateResource::make($template)
        ]);
    }

    public function duplicate(Request $request, $id)
    {
        $template = $this->service->duplicate((int) $id);

        return Response::create()->json([
            'message' => __('Template duplicated successfully', 'elemacy'),
            'data' => TemplateResource::make($template)
        ], Response::CREATED);
    }

    public function destroy(Request $request, $id)
    {
        $this->service->delete((int) $id);

        return Response::create()->json([
            'message' => __('Template deleted successfully', 'elemacy')
        ]);
    }
}
