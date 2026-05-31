<?php

namespace Elemacy\Modules\Popups\Resources;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elemacy\Core\Resource;
use Elemacy\Modules\Popups\DTO\PopupDTO;
use Elemacy\Modules\Popups\DTO\RuleDTO;
use Elemacy\Modules\Popups\DTO\TriggerDTO;
use Elemacy\Modules\Popups\Services\RuleManager;
use Elemacy\Modules\Popups\Services\TriggerManager;
use Elemacy\Modules\Popups\Support\DocumentDisplay;

/**
 * Per-popup configuration contract handed to the frontend engine (engine.js)
 * via wp_localize_script.
 */
class PopupConfigResource extends Resource
{
    /**
     * @return array
     */
    public function to_array()
    {
        /** @var PopupDTO $popup */
        $popup = $this->resource;

        $config = [
            'id'          => (int) $popup->id,
            'type'        => $popup->type,
            'triggers'    => $this->normalize_triggers(is_array($popup->triggers) ? $popup->triggers : []),
            'rules'       => $this->normalize_rules(is_array($popup->rules) ? $popup->rules : []),
            'display'     => DocumentDisplay::for_popup((int) $popup->id),
            'storage_key' => 'elemacy_popup_' . $popup->id,
        ];

        /**
         * Filter the frontend config emitted for a single popup.
         *
         * @param array    $config
         * @param PopupDTO $popup
         */
        return apply_filters(Hooks::POPUP_FRONTEND_CONFIG_FILTER, $config, $popup);
    }

    /**
     * Normalize each stored trigger into the flat engine config via its
     * registered handler. Unregistered triggers fall back to their raw form so
     * nothing silently disappears.
     *
     * @param array $triggers
     * @return array<int, array<string, mixed>>
     */
    protected function normalize_triggers(array $triggers): array
    {
        $manager = TriggerManager::instance();
        $out     = [];

        foreach (TriggerDTO::collection($triggers) as $dto) {
            $trigger = $manager->get($dto->type);

            $out[] = $trigger ? $trigger->to_js_config($dto) : $dto->to_array();
        }

        return $out;
    }

    /**
     * Normalize each stored rule into the engine config. Server-evaluable rules
     * are enforced server-side and therefore excluded from the client payload.
     * Unregistered rules fall back to their raw form.
     *
     * @param array $rules
     * @return array<int, array<string, mixed>>
     */
    protected function normalize_rules(array $rules): array
    {
        $manager = RuleManager::instance();
        $out     = [];

        foreach (RuleDTO::collection($rules) as $dto) {
            $rule = $manager->get($dto->type);

            if ($rule && $rule->is_server_evaluable()) {
                continue;
            }

            $out[] = $rule ? $rule->to_js_config($dto) : $dto->to_array();
        }

        return $out;
    }
}
