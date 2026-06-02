<?php

namespace Elemacy\Conditions;

defined('ABSPATH') || exit;

use Elemacy\Conditions\DTO\ConditionRuleDTO;

class ConditionEvaluator
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
     * Flat matching model (mirrors Elementor, no AND/grouping): includes are
     * OR'd, any exclude match vetoes, and an exclude-only set is based on "show
     * everywhere". Unknown types are skipped — free ships a mock per pro
     * condition under the same name, so deactivating pro just makes its
     * includes hide the template (safe) rather than leaving the type unresolved.
     *
     * @param ConditionRuleDTO[] $conditions
     */
    public function evaluate(array $conditions): bool
    {
        $manager     = ConditionManager::instance();
        $has_include = false;
        $included    = false;

        foreach ($conditions as $rule) {
            $condition = $manager->get($rule->type);

            if (!$condition) {
                continue;
            }

            $matches = $condition->check($rule);

            if ($rule->is_exclude()) {
                if ($matches) {
                    return false;
                }

                continue;
            }

            $has_include = true;

            if ($matches) {
                $included = true;
            }
        }

        return $has_include ? $included : true;
    }
}
