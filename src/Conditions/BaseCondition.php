<?php

namespace Elemacy\Conditions;

defined('ABSPATH') || exit;

use Elemacy\Conditions\DTO\ConditionRuleDTO;

abstract class BaseCondition implements ConditionInterface
{
    /**
     * The broadest level, so a condition that never declares one — including a
     * third party's — still registers and still matches, and simply never
     * outranks a condition that does declare one.
     */
    public function get_specificity(ConditionRuleDTO $rule): int
    {
        return Specificity::SITE;
    }

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
     * Whether a rule for this condition must name a value. One that does not
     * would match the same requests as a broader condition (Specific Post
     * with no post is every singular page, which is Singular's job), so a new
     * rule without a value is refused when saved. Lives here rather than on
     * ConditionInterface, so third-party conditions keep working unchanged.
     */
    public function requires_value(): bool
    {
        return false;
    }

    /**
     * Default applicability inferred from {@see ConditionInterface::get_type()}.
     *
     *  - `general`  → site-wide templates only (header, footer).
     *  - `singular` → site-wide templates + the generic Single template.
     *  - `archive`  → site-wide templates + the generic Archive template.
     *  - `search`   → the Search Results template only.
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
            case 'search':
                return ['search'];
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
