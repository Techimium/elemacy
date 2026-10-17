<?php

namespace Elemacy\Modules\Popups\Resources;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionManager;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Http\Resource;
use Elemacy\Modules\Popups\DTO\RuleDTO;
use Elemacy\Modules\Popups\DTO\TriggerDTO;
use Elemacy\Modules\Popups\Services\RuleManager;
use Elemacy\Modules\Popups\Services\TriggerManager;
use Elemacy\TemplateLibrary\TypeRegistry;

class PopupResource extends Resource
{
    public function to_array()
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            // : lets the admin app tell a repairable unknown stored
            // type apart from one of the live registry choices, the same way
            // conditions/triggers/rules below already do.
            'type_availability' => TypeRegistry::instance()->get_availability((string) $this->type)->to_array(),
            'status' => $this->status,
            'author' => $this->author,
            'date' => $this->date,
            'edit_with_elementor' => admin_url('post.php?post=' . $this->id . '&action=elementor'),
            'conditions' => self::with_availability(
                ConditionRuleDTO::to_arrays($this->conditions ?? []),
                static fn (string $type) => ConditionManager::instance()->get_availability($type)
            ),
            'triggers' => self::with_availability(
                TriggerDTO::to_arrays($this->triggers ?? []),
                static fn (string $type) => TriggerManager::instance()->get_availability($type)
            ),
            'rules' => self::with_availability(
                RuleDTO::to_arrays($this->rules ?? []),
                static fn (string $type) => RuleManager::instance()->get_availability($type)
            ),
        ];
    }

    /**
     * Annotates each stored entry with its current {@see \Elemacy\Core\Capabilities\FeatureAvailability}
     * so the admin app can identify an unavailable saved condition/rule/trigger
     * instead of it merely appearing unset.
     *
     * @param array<int, array{type: string}>                                  $entries
     * @param callable(string): \Elemacy\Core\Capabilities\FeatureAvailability $resolve
     * @return array<int, array<string, mixed>>
     */
    private static function with_availability(array $entries, callable $resolve): array
    {
        return array_map(static function (array $entry) use ($resolve): array {
            $entry['availability'] = $resolve((string) $entry['type'])->to_array();

            return $entry;
        }, $entries);
    }
}
