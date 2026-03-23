<?php

namespace Elemacy\Modules\ThemeBuilder\Controllers;

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

class ThemeBuilderController
{
    protected $service;

    public function __construct()
    {
        $this->service = new TemplateService();
    }

    public function index(Request $request)
    {
        $filter_dto = TemplateListFilterDTO::from_array($request->all());
        $templates = $this->service->get_all($filter_dto);

        return Response::create()->json([
            'data' => TemplateListResource::collection($templates),
            'message' => 'Templates retrieved successfully'
        ]);
    }

    public function show(Request $request, $id)
    {
        $template = $this->service->get((int) $id);

        if (!$template) {
            return Response::create()->json([
                'message' => 'Template not found'
            ], Response::NOT_FOUND);
        }

        return Response::create()->json([
            'data' => TemplateResource::make($template),
            'message' => 'Template retrieved successfully'
        ]);
    }

    public function store(CreateTemplateRequest $request)
    {
        $dto = CreateTemplateDTO::from_request($request);
        $result = $this->service->create($dto);

        if (is_wp_error($result)) {
            return Response::create()->json([
                'message' => $result->get_error_message()
            ], Response::INTERNAL_SERVER_ERROR);
        }

        return Response::create()->json([
            'message' => 'Template created successfully',
            'data' => TemplateResource::make($result)
        ], Response::CREATED);
    }

    public function update(UpdateTemplateRequest $request, $id)
    {
        $dto = UpdateTemplateDTO::from_request($request);
        $result = $this->service->update((int) $id, $dto);

        if (is_wp_error($result)) {
            $status = $result->get_error_code() === 'not_found' ? Response::NOT_FOUND : Response::INTERNAL_SERVER_ERROR;
            return Response::create()->json([
                'message' => $result->get_error_message()
            ], $status);
        }

        return Response::create()->json([
            'message' => 'Template updated successfully',
            'data' => TemplateResource::make($result)
        ]);
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
            'message' => 'Template deleted successfully'
        ]);
    }
}
