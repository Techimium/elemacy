<?php

namespace Elemacy\Modules\ThemeBuilder\Services;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionEvaluator;
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

            if (ConditionEvaluator::instance()->evaluate($template->conditions)) {
                return $template;
            }
        }

        return $fallback;
    }
}
