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
     * Evaluate a set of condition rules against the current request.
     *
     * A single exclude match vetoes the template. Include rules are OR'd. When a
     * rule set has no include rules (excludes only), the base is "show everywhere"
     * so excludes subtract from it — otherwise an exclude-only set could never match.
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
