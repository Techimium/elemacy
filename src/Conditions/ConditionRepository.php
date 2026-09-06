<?php

namespace Elemacy\Conditions;

defined('ABSPATH') || exit;

use Elemacy\Conditions\DTO\ConditionRuleDTO;

/**
 * The one place display-condition rules are persisted, so ThemeBuilder templates
 * and Popups read/write them the same way instead of re-implementing storage.
 */
class ConditionRepository
{
    public const META_KEY = '_elemacy_conditions';

    /**
     * @return ConditionRuleDTO[]
     */
    public function get(int $post_id): array
    {
        $raw = get_post_meta($post_id, static::META_KEY, true);

        return ConditionRuleDTO::collection(is_array($raw) ? $raw : []);
    }

    /**
     * @param array $rules ConditionRuleDTOs and/or their array form.
     */
    public function save(int $post_id, array $rules): void
    {
        update_post_meta($post_id, static::META_KEY, $this->normalize($rules));
    }

    protected function normalize(array $rules): array
    {
        return array_values(array_map(function ($rule): array {
            $dto = ConditionRuleDTO::from_array(is_array($rule) ? $rule : []);
            $dto->id = $dto->id !== '' ? $dto->id : wp_generate_uuid4();

            return $dto->to_array();
        }, $rules));
    }
}
