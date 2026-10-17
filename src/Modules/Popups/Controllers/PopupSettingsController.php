<?php

namespace Elemacy\Modules\Popups\Controllers;

defined('ABSPATH') || exit;

use Elemacy\Http\Request;
use Elemacy\Http\Response;
use Elemacy\Modules\Popups\Resources\RuleTypeResource;
use Elemacy\Modules\Popups\Resources\TriggerTypeResource;
use Elemacy\Modules\Popups\Services\PopupManager;
use Elemacy\Modules\Popups\Services\RuleManager;
use Elemacy\Modules\Popups\Services\TriggerManager;

class PopupSettingsController
{
    public function types(Request $request) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- The router passes every handler the request.
    {
        return Response::create()->json([
            'data' => PopupManager::instance()->get_available_popup_types(),
        ]);
    }

    public function triggers(Request $request) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- The router passes every handler the request.
    {
        return Response::create()->json([
            'data' => TriggerTypeResource::collection(
                array_values(TriggerManager::instance()->get_all())
            ),
        ]);
    }

    public function rules(Request $request) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- The router passes every handler the request.
    {
        return Response::create()->json([
            'data' => RuleTypeResource::collection(
                array_values(RuleManager::instance()->get_all())
            ),
        ]);
    }
}
