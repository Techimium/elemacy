<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionEvaluator;
use Elemacy\Core\Hooks;
use Elemacy\Modules\Popups\DTO\PopupDTO;
use Elemacy\Modules\Popups\DTO\PopupListFilterDTO;
use Elemacy\Modules\Popups\DTO\RuleDTO;
use Elemacy\Modules\Popups\Services\RuleManager;

/**
 * Resolves which published popups match the current request based on their
 * display conditions.
 */
class PopupDisplayResolver
{
    protected static ?self $instance = null;

    public static function instance(): self
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Return every published popup whose display conditions match the current
     * request.
     *
     * A popup with no conditions is treated as global (shows everywhere).
     * Otherwise the shared condition engine decides (include-OR / exclude-AND /
     * no-include = show everywhere).
     *
     * @return PopupDTO[]
     */
    public function resolve_all(): array
    {
        $filter         = new PopupListFilterDTO();
        $filter->status = 'publish';

        $popups  = (new PopupService())->get_all($filter);
        $matched = [];

        foreach ($popups as $popup) {
            if (!$this->matches($popup)) {
                continue;
            }

            $matched[] = $popup;
        }

        /**
         * Filter the list of popups that matched the current request.
         *
         * @param PopupDTO[] $matched
         */
        return apply_filters(Hooks::POPUP_MATCHED_FILTER, $matched);
    }

    /**
     * Decide whether a single popup should display on the current request.
     */
    protected function matches(PopupDTO $popup): bool
    {
        // Server-evaluable advanced rules (logged-in, etc.) gate the popup
        // before it is handed to the frontend. Client-side rules (frequency,
        // devices, …) are enforced in engine.js.
        if (!$this->passes_server_rules($popup)) {
            return false;
        }

        if (empty($popup->conditions)) {
            return true;
        }

        return ConditionEvaluator::instance()->evaluate($popup->conditions);
    }

    /**
     * Drop the popup if any server-evaluable rule attached to it fails.
     */
    protected function passes_server_rules(PopupDTO $popup): bool
    {
        $rules = is_array($popup->rules) ? $popup->rules : [];

        if (empty($rules)) {
            return true;
        }

        $manager = RuleManager::instance();

        foreach (RuleDTO::collection($rules) as $dto) {
            $rule = $manager->get($dto->type);

            if ($rule && $rule->is_server_evaluable() && !$rule->check($dto)) {
                return false;
            }
        }

        return true;
    }
}
