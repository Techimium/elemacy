<?php

namespace Elemacy\Modules\ThemeBuilder\Services;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\DTO\ConditionRuleDTO;
use Elemacy\Modules\ThemeBuilder\DTO\TemplateListFilterDTO;

class TemplateConditionResolver
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
     * Pick the published template of the given type whose conditions match the
     * current request. Templates without conditions act as the global fallback.
     *
     * @return object|null
     */
    public function resolve(string $type)
    {
        $filter         = new TemplateListFilterDTO();
        $filter->type   = $type;
        $filter->status = 'publish';

        $templates = (new TemplateService())->get_all($filter);
        $fallback  = null;

        foreach ($templates as $template) {
            if (empty($template->conditions)) {
                $fallback = $fallback ?? $template;
                continue;
            }

            if ($this->evaluate($template->conditions)) {
                return $template;
            }
        }

        return $fallback;
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
    protected function evaluate(array $conditions): bool
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
