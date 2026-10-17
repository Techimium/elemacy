<?php

namespace Elemacy\Conditions;

defined('ABSPATH') || exit;

use Elemacy\Conditions\DTO\ConditionRuleDTO;

class ConditionEvaluator
{
    protected static ?self $instance = null;

    protected ConditionManager $manager;

    public function __construct(?ConditionManager $manager = null)
    {
        $this->manager = $manager ?? ConditionManager::instance();
    }

    public static function instance(): self
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Flat matching model (mirrors Elementor, no AND/grouping): includes are
     * OR'd, any exclude match vetoes, and an exclude-only set is based on
     * "show everywhere".
     *
     * @param ConditionRuleDTO[] $conditions
     */
    public function evaluate(array $conditions): bool
    {
        return $this->match($conditions) !== null;
    }

    /**
     * {@see evaluate()}'s matching model, reporting how narrowly the match was
     * made rather than only whether it was made at all: the highest
     * {@see Specificity} level among the inclusions that actually matched.
     *
     * An exclusion never contributes a level — it can only veto — so a broad
     * template carrying a narrow exclusion never outranks a genuinely narrow
     * one. An exclude-only set still means "show everywhere" and so reports
     * the broadest level, as does an empty set.
     *
     * @param ConditionRuleDTO[] $conditions
     * @return int|null The matched specificity, or null when this set does not
     *                  match the current request at all.
     */
    public function match(array $conditions): ?int
    {
        $manager = $this->manager;

        foreach ($conditions as $rule) {
            if ($manager->get_availability($rule->type)->is_unavailable()) {
                return null;
            }
        }

        $has_include = false;
        $specificity = null;

        foreach ($conditions as $rule) {
            $condition = $manager->get($rule->type);
            $matches   = $condition->check($rule);

            if ($rule->is_exclude()) {
                if ($matches) {
                    return null;
                }

                continue;
            }

            $has_include = true;

            if (!$matches) {
                continue;
            }

            $level = $condition->get_specificity($rule);

            $specificity = $specificity === null ? $level : max($specificity, $level);
        }

        if (!$has_include) {
            return Specificity::SITE;
        }

        return $specificity;
    }
}
