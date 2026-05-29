<?php

namespace Elemacy\Modules\ThemeBuilder\Controllers;

defined('ABSPATH') || exit;

use Elemacy\Core\Http\Request;
use Elemacy\Core\Http\Response;
use Elemacy\Modules\ThemeBuilder\Resources\ConditionGroupResource;
use Elemacy\Modules\ThemeBuilder\Services\ConditionManager;

class ConditionController
{
    public function types(Request $request): Response
    {
        $template_type = (string) $request->get_string('template_type', '');
        $result        = [];

        foreach (ConditionManager::instance()->get_grouped() as $type => $conditions) {
            $applicable = array_filter(
                $conditions,
                static fn ($condition): bool => $template_type === '' || $condition->applies_to($template_type)
            );

            if (empty($applicable)) {
                continue;
            }

            $result[] = [
                'type'       => $type,
                'conditions' => $applicable,
            ];
        }

        return Response::create()->json(['data' => ConditionGroupResource::collection($result)]);
    }
}
