<?php

namespace Elemacy\Core\Controllers;

use Elemacy\Core\Contracts\Request;
use Elemacy\Core\Http\Response;
use Elemacy\Core\Elemacy;

class ModuleController {
    public function index() {
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

    public function toggle(Request $request) {
        $module_name = $request->get_string('module_name');
        $action = $request->get_string('action');

        $module_manager = Elemacy::get_instance()->get_module_manager();

        if ($action === 'enable') {
            $result = $module_manager->enable_module($module_name);
        } elseif ($action === 'disable') {
            $result = $module_manager->disable_module($module_name);
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