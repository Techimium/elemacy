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
        $manager = $this->manager;

        foreach ($conditions as $rule) {
            if ($manager->get_availability($rule->type)->is_unavailable()) {
                return false;
            }
        }

        $has_include = false;
        $included    = false;

        foreach ($conditions as $rule) {
            $condition = $manager->get($rule->type);
            $matches   = $condition->check($rule);

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
