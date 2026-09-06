<?php

namespace Elemacy\Modules\Popups\Controllers;

defined('ABSPATH') || exit;

use Elemacy\Core\Http\Request;
use Elemacy\Core\Http\Response;
use Elemacy\Modules\Popups\Resources\RuleTypeResource;
use Elemacy\Modules\Popups\Resources\TriggerTypeResource;
use Elemacy\Modules\Popups\Services\RuleManager;
use Elemacy\Modules\Popups\Services\TriggerManager;

class PopupSettingsController
{
    public function triggers(Request $request)
    {
        return Response::create()->json([
            'data' => TriggerTypeResource::collection(
                array_values(TriggerManager::instance()->get_all())
            ),
        ]);
    }

    public function rules(Request $request)
    {
        return Response::create()->json([
            'data' => RuleTypeResource::collection(
                array_values(RuleManager::instance()->get_all())
            ),
        ]);
    }
}
