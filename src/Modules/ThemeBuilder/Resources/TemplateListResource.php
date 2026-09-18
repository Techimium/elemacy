<?php

namespace Elemacy\Modules\ThemeBuilder\Resources;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionManager;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Core\Resource;

class TemplateListResource extends Resource
{
    public function to_array()
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'status' => $this->status,
            'author' => $this->author,
            'date' => $this->date,
            'edit_with_elementor' => admin_url('post.php?post=' . $this->id . '&action=elementor'),
            'conditions' => self::conditions_with_availability($this->conditions ?? []),
        ];
    }

    /**
     * Annotates each stored condition with its current
     * {@see \Elemacy\Core\Capabilities\FeatureAvailability} so the admin app
     * can identify an unavailable saved condition (D5) instead of it merely
     * appearing unset.
     *
     * @param \Elemacy\Conditions\DTO\ConditionRuleDTO[] $conditions
     * @return array<int, array<string, mixed>>
     */
    private static function conditions_with_availability(array $conditions): array
    {
        $manager = ConditionManager::instance();

        return array_map(static function (array $entry) use ($manager): array {
            $entry['availability'] = $manager->get_availability((string) $entry['type'])->to_array();

            return $entry;
        }, ConditionRuleDTO::to_arrays($conditions));
    }
}
