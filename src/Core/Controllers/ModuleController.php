<?php

namespace Elemacy\Core\Controllers;

defined('ABSPATH') || exit;

use Elemacy\Core\Elemacy;
use Elemacy\Core\Exceptions\InvalidRoutActionException;
use Elemacy\Core\Exceptions\ModuleNotFoundException;
use Elemacy\Core\Http\Request;
use Elemacy\Core\Http\Response;

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
                'icon' => $module->get_icon(),
                'description' => $module->get_description(),
                'is_active' => $module_manager->is_active($module->get_name()),
                'is_headless' => $module->is_headless(),
            ];
        }

        return Response::create()->json([
            'data' => $data,
            'message' => 'Modules fetched successfully',
        ]);
    }

    public function show(Request $request, $name)
    {
        $module_manager = Elemacy::get_instance()->get_module_manager();
        $module = $module_manager->get_module($name);

        if (!$module) {
            throw new ModuleNotFoundException(
                sprintf(__('Module "%s" not found.', 'elemacy'), $name),
                Response::NOT_FOUND
            );
        }

        return Response::create()->json([
            'data' => [
                'name' => $module->get_name(),
                'title' => $module->get_title(),
                'description' => $module->get_description(),
                'is_active' => $module_manager->is_active($module->get_name()),
            ],
            'message' => 'Module fetched successfully',
        ]);
    }

    public function toggle(Request $request, $name)
    {
        $action = $request->get_string('action');
        $module_manager = Elemacy::get_instance()->get_module_manager();

        if ($action === 'enable') {
            $module_manager->enable_module($name);
        } elseif ($action === 'disable') {
            $module_manager->disable_module($name);
        } else {
            throw new InvalidRoutActionException(
                __('Invalid action.', 'elemacy'),
                Response::BAD_REQUEST
            );
        }

        return Response::create()->json([
            'message' => 'Module toggled successfully',
        ]);
    }
}
