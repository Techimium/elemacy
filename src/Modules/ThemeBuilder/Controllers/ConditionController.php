<?php

namespace Elemacy\Modules\ThemeBuilder\Controllers;

defined('ABSPATH') || exit;

use Elemacy\Core\Http\Request;
use Elemacy\Core\Http\Response;
use Elemacy\Modules\ThemeBuilder\Resources\ConditionGroupResource;
use Elemacy\Modules\ThemeBuilder\Services\ConditionManager;
use Elemacy\Modules\ThemeBuilder\Services\ConditionSearchService;

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

    /**
     * Backs the async value combobox for `search`-type conditions. The search
     * scope is taken from the condition's own config (keyed by name) so the
     * client cannot widen the query.
     */
    public function search(Request $request): Response
    {
        $name      = (string) $request->get_string('condition', '');
        $query     = (string) $request->get_string('q', '');
        $condition = ConditionManager::instance()->get($name);

        if (!$condition || $condition->get_value_type() !== 'search') {
            return Response::create()->json(['data' => []]);
        }

        $include = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $request->get_string('include', ''))
        )));

        $results = (new ConditionSearchService())->search($condition->get_search_config(), $query, $include);

        return Response::create()->json(['data' => $results]);
    }
}
