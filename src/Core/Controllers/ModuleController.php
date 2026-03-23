<?php

namespace Elemacy\Core\Controllers;

use Elemacy\Core\Http\Request;
use Elemacy\Core\Http\Response;
use Elemacy\Core\Elemacy;

class ModuleController
{
    public function index()
    {
        $module_manager = Elemacy::get_instance()->get_module_manager();
        $modules = $module_manager->get_modules();
        $data = [];

        foreach ($modules as $module) {
            $data[] = [
                'name' => $module->get_name(),
                'title' => $module->get_title(),
                'description' => $module->get_description(),
                'dependencies' => $module->get_dependencies(),
                'is_active' => $module_manager->is_active($module->get_name())
            ];
        }

        return Response::create()->json([
            'data' => $data,
            'message' => 'Modules fetched successfully'
        ]);
    }

    public function show(Request $request, $name)
    {
        $module_manager = Elemacy::get_instance()->get_module_manager();
        $module = $module_manager->get_module($name);

        if (!$module) {
            return Response::create()->json([
                'message' => 'Module not found'
            ], Response::NOT_FOUND);
        }

        return Response::create()->json([
            'data' => [
                'name' => $module->get_name(),
                'title' => $module->get_title(),
                'description' => $module->get_description(),
                'dependencies' => $module->get_dependencies(),
                'is_active' => $module_manager->is_active($module->get_name())
            ],
            'message' => 'Module fetched successfully'
        ]);
    }

    public function toggle(Request $request, $name)
    {
        $action = $request->get_string('action');

        $module_manager = Elemacy::get_instance()->get_module_manager();

        if ($action === 'enable') {
            $result = $module_manager->enable_module($name);
        } elseif ($action === 'disable') {
            $result = $module_manager->disable_module($name);
        } else {
            return Response::create()->json([
                'message' => 'Invalid action'
            ], Response::BAD_REQUEST);
        }

        if (is_wp_error($result)) {
            return Response::create()->json([
                'message' => $result->get_error_message()
            ], Response::UNPROCESSABLE_ENTITY);
        }

        return Response::create()->json([
            'message' => 'Module toggled successfully'
        ]);
    }
}
