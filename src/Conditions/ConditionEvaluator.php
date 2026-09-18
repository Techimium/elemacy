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
     * OR'd, any exclude match vetoes, and an exclude-only set is based on "show
     * everywhere".
     *
     * Every rule's identifier must be available before any include/exclude
     * matching happens: a rule that is unregistered, or resolves only to a
     * non-functional mock stand-in, suspends the whole set rather than being
     * skipped. Skipping would let an unavailable-only include set silently
     * become "show everywhere", and let an unavailable exclude silently stop
     * vetoing — both would broaden who sees the dependent item, which D5
     * forbids. This never treats a missing capability as a satisfied (or
     * failed) predicate: it is a distinct, unavailable status, checked before
     * any `check()` call.
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
