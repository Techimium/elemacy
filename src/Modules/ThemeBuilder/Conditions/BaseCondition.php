<?php

namespace Elemacy\Modules\ThemeBuilder\Conditions;

defined('ABSPATH') || exit;

abstract class BaseCondition implements ConditionInterface
{
    public function get_sub_values(): array
    {
        return [];
    }

    /**
     * Defaults to "has a value" when the condition exposes sub-values.
     * Override to expose a free-form value input without predefined sub-values.
     */
    public function has_value(): bool
    {
        return !empty($this->get_sub_values());
    }

    public function is_mock(): bool
    {
        return false;
    }

    /**
     * Default applicability inferred from {@see ConditionInterface::get_type()}.
     *
     *  - `general`  → site-wide templates only (header, footer).
     *  - `singular` → site-wide templates + the generic Single template.
     *  - `archive`  → site-wide templates + the generic Archive template.
     *
     * Concrete conditions override this only when the default does not fit.
     *
     * @return string[]
     */
    public function get_applicable_template_types(): array
    {
        switch ($this->get_type()) {
            case 'singular':
                return ['header', 'footer', 'single'];
            case 'archive':
                return ['header', 'footer', 'archive'];
            case 'general':
            default:
                return ['header', 'footer'];
        }
    }

    public function applies_to(string $template_type): bool
    {
        $applicable = $this->get_applicable_template_types();

        return in_array('*', $applicable, true) || in_array($template_type, $applicable, true);
    }
}
