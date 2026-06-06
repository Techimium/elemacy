<?php

namespace Elemacy\Core\Controllers;

defined('ABSPATH') || exit;

use Elemacy\Core\Elemacy;
use Elemacy\Core\Exceptions\InvalidRoutActionException;
use Elemacy\Core\Http\Request;
use Elemacy\Core\Http\Response;

class ModuleController
{
    public function index()
    {
        $module_manager = Elemacy::get_instance()->get_module_manager();

        return Response::create()->json([
            'data' => $module_manager->to_array(),
            'message' => 'Modules fetched successfully',
        ]);
    }

    public function toggle(Request $request, $name)
    {
        $action = $request->get_string('action');
        $module_manager = Elemacy::get_instance()->get_module_manager();

        $module = $module_manager->get_module($name);
        if ($module && $module->is_placeholder()) {
            throw new InvalidRoutActionException(
                esc_html__('This module is not available yet.', 'elemacy'),
                Response::FORBIDDEN // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- HTTP status code, not output.
            );
        }

        if ($action === 'enable') {
            $module_manager->enable_module($name);
        } elseif ($action === 'disable') {
            $module_manager->disable_module($name);
        } else {
            throw new InvalidRoutActionException(
                esc_html__('Invalid action.', 'elemacy'),
                Response::BAD_REQUEST // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- HTTP status code, not output.
            );
        }

        return Response::create()->json([
            'message' => 'Module toggled successfully',
        ]);
    }
}
