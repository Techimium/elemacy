<?php

namespace Elemacy\Core\Controllers;

defined('ABSPATH') || exit;

use Elemacy\Core\Elemacy;
use Elemacy\Core\Exceptions\HttpException;
use Elemacy\Core\Http\Request;
use Elemacy\Core\Http\Response;

class ModuleController
{
    /**
     * Lists every registered module and its active/mock/headless state.
     *
     * @return Response
     */
    public function index()
    {
        $module_manager = Elemacy::get_instance()->get_module_manager();

        return Response::create()->json([
            'data' => $module_manager->to_array(),
            'message' => esc_html__('Modules fetched successfully', 'elemacy'),
        ]);
    }

    /**
     * Enables or disables a module.
     *
     * @param Request $request The incoming REST request; expects an `action` param of `enable`/`disable`.
     * @param string  $name    The module slug to toggle.
     * @return Response
     * @throws HttpException If the module is a free-tier mock, or `action` isn't `enable`/`disable`.
     */
    public function toggle(Request $request, $name)
    {
        $action = $request->get_string('action');
        $module_manager = Elemacy::get_instance()->get_module_manager();
        $module = $module_manager->get_module($name);

        if ($module && $module->is_mock()) {
            throw new HttpException(
                esc_html__('This module is not available yet.', 'elemacy'),
                Response::FORBIDDEN // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- HTTP status code, not output.
            );
        }

        if ($action === 'enable') {
            $module_manager->enable_module($name);
        } elseif ($action === 'disable') {
            $module_manager->disable_module($name);
        } else {
            throw new HttpException(
                esc_html__('Invalid action.', 'elemacy'),
                Response::BAD_REQUEST // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- HTTP status code, not output.
            );
        }

        return Response::create()->json([
            'message' => esc_html__('Module toggled successfully', 'elemacy'),
        ]);
    }
}
