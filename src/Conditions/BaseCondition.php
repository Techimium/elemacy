<?php

namespace Elemacy\Conditions;

defined('ABSPATH') || exit;

abstract class BaseCondition implements ConditionInterface
{
    public function get_sub_values(): array
    {
        return [];
    }

    /**
     * Defaults to a `select` when the condition exposes sub-values, otherwise
     * `none`. Search-backed conditions override this to return `search` and
     * supply {@see get_search_config()}.
     */
    public function get_value_type(): string
    {
        return !empty($this->get_sub_values()) ? 'select' : 'none';
    }

    public function get_search_config(): array
    {
        return [];
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

    public function applies_to(string $context = ''): bool
    {
        // Popups can target by any display condition; theme-builder filters by template type.
        if ($context === '' || $context === 'popup') {
            return true;
        }

        $applicable = $this->get_applicable_template_types();

        return in_array('*', $applicable, true) || in_array($context, $applicable, true);
    }
}
