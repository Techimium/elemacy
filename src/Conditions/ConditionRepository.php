<?php

namespace Elemacy\Conditions;

defined('ABSPATH') || exit;

use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Http\Exceptions\ValidationException;

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
     * @param int   $post_id The library item whose targeting is replaced.
     * @param array $rules ConditionRuleDTOs and/or their array form.
     * @throws \Elemacy\Http\Exceptions\ValidationException Unsupported input
     *   (neither a ConditionRuleDTO nor an array) rejects the whole write —
     *   the previously stored targeting is left untouched, never partially
     *   overwritten with the supported entries alone.
     */
    public function save(int $post_id, array $rules): void
    {
        update_post_meta($post_id, static::META_KEY, $this->normalize($rules));
    }

    /**
     * Rejects a new rule that names no condition, or that leaves out the value
     * its condition requires (BaseCondition::requires_value()). A rule already
     * stored on $post_id is let through unchanged even when blank, unknown or
     * valueless, so saving an unrelated field never fails and never rewrites
     * it; it stays preserved and suspends the item, or matches nothing, as
     * before. Templates and popups both check their rules here before saving.
     *
     * @param int   $post_id The item being updated, or 0 when creating.
     * @param array $rules ConditionRuleDTOs and/or their array form.
     * @throws ValidationException A new rule has a blank condition type or a missing required value.
     */
    public function assert_new_rules_are_complete(int $post_id, array $rules): void
    {
        $stored = [];

        foreach ($post_id > 0 ? $this->get($post_id) : [] as $existing) {
            $stored[] = $existing->to_array();
        }

        foreach (ConditionRuleDTO::collection_or_fail($rules) as $rule) {
            if (in_array($rule->to_array(), $stored, true)) {
                continue;
            }

            if (trim($rule->type) === '') {
                throw ValidationException::with_errors([
                    'conditions' => [esc_html__('Each display condition needs a type.', 'elemacy')],
                ]);
            }

            $condition = ConditionManager::instance()->get($rule->type);

            if ($condition instanceof BaseCondition && $condition->requires_value() && trim((string) $rule->value) === '') {
                $message = sprintf(
                    /* translators: %s: display condition name, e.g. "Specific Post / Page". */
                    __('Choose which post "%s" applies to.', 'elemacy'),
                    $condition->get_label()
                );

                throw ValidationException::with_errors([
                    'conditions' => [esc_html($message)],
                ]);
            }
        }
    }

    protected function normalize(array $rules): array
    {
        return array_map(function (ConditionRuleDTO $dto): array {
            $dto->id = $dto->id !== '' ? $dto->id : wp_generate_uuid4();

            return $dto->to_array();
        }, ConditionRuleDTO::collection_or_fail($rules));
    }
}
