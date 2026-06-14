<?php

namespace Elemacy\TemplateLibrary\Controllers;

defined('ABSPATH') || exit;

use Elemacy\Core\Http\Request;
use Elemacy\Core\Http\Response;
use Elemacy\TemplateLibrary\DTO\BlockTemplateListFilterDTO;
use Elemacy\TemplateLibrary\DTO\CreateBlockTemplateDTO;
use Elemacy\TemplateLibrary\DTO\UpdateBlockTemplateDTO;
use Elemacy\TemplateLibrary\Requests\CreateBlockTemplateRequest;
use Elemacy\TemplateLibrary\Requests\UpdateBlockTemplateRequest;
use Elemacy\TemplateLibrary\Resources\BlockTemplateResource;
use Elemacy\TemplateLibrary\Services\BlockTemplateService;
use Elemacy\TemplateLibrary\TypeRegistry;

class LibraryController
{
    protected BlockTemplateService $service;

    public function __construct()
    {
        $this->service = new BlockTemplateService();
    }

    public function types()
    {
        $types = array_map(
            static fn ($definition): array => [
                'value' => $definition->name,
                'label' => $definition->label,
            ],
            TypeRegistry::instance()->by_group(BlockTemplateService::GROUP)
        );

        return Response::create()->json([
            'data' => array_values($types),
        ]);
    }

    public function index(Request $request)
    {
        $templates = $this->service->get_all(BlockTemplateListFilterDTO::from_array($request->all()));

        return Response::create()->json([
            'data' => BlockTemplateResource::collection($templates),
            'message' => __('Templates retrieved successfully', 'elemacy'),
        ]);
    }

    public function show(Request $request, $id)
    {
        $template = $this->service->get_or_fail((int) $id);

        return Response::create()->json([
            'data' => BlockTemplateResource::make($template),
            'message' => __('Template retrieved successfully', 'elemacy'),
        ]);
    }

    public function store(CreateBlockTemplateRequest $request)
    {
        $template = $this->service->create(CreateBlockTemplateDTO::from_array($request->clean()));

        return Response::create()->json([
            'message' => __('Template created successfully', 'elemacy'),
            'data' => BlockTemplateResource::make($template),
        ], Response::CREATED);
    }

    public function update(UpdateBlockTemplateRequest $request, $id)
    {
        $template = $this->service->update((int) $id, UpdateBlockTemplateDTO::from_array($request->clean()));

        return Response::create()->json([
            'message' => __('Template updated successfully', 'elemacy'),
            'data' => BlockTemplateResource::make($template),
        ]);
    }

    public function duplicate(Request $request, $id)
    {
        $template = $this->service->duplicate((int) $id);

        return Response::create()->json([
            'message' => __('Template duplicated successfully', 'elemacy'),
            'data' => BlockTemplateResource::make($template),
        ], Response::CREATED);
    }

    public function destroy(Request $request, $id)
    {
        $this->service->delete((int) $id);

        return Response::create()->json([
            'message' => __('Template deleted successfully', 'elemacy'),
        ]);
    }
}
