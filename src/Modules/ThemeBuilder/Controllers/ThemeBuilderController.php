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
            'message' => __('Templates retrieved successfully', 'elemacy')
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
            'message' => __('Template retrieved successfully', 'elemacy')
        ]);
    }

    public function store(CreateTemplateRequest $request)
    {
        $data = $request->clean();
        $result = $this->service->create(CreateTemplateDTO::from_array($data));

        if (is_wp_error($result)) {
            return Response::create()->json([
                'message' => $result->get_error_message()
            ], Response::INTERNAL_SERVER_ERROR);
        }

        return Response::create()->json([
            'message' => __('Template created successfully', 'elemacy'),
            'data' => TemplateResource::make($result)
        ], Response::CREATED);
    }

    public function update(UpdateTemplateRequest $request, $id)
    {
        $data = $request->clean();
        $result = $this->service->update((int) $id, UpdateTemplateDTO::from_array($data));

        if (is_wp_error($result)) {
            $status = $result->get_error_code() === 'not_found' ? Response::NOT_FOUND : Response::INTERNAL_SERVER_ERROR;
            return Response::create()->json([
                'message' => $result->get_error_message()
            ], $status);
        }

        return Response::create()->json([
            'message' => __('Template updated successfully', 'elemacy'),
            'data' => TemplateResource::make($result)
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
            'message' => __('Template duplicated successfully', 'elemacy'),
            'data' => TemplateResource::make($result)
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
            'message' => __('Template deleted successfully', 'elemacy')
        ]);
    }
}
