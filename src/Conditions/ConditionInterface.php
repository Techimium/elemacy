<?php

namespace Elemacy\Conditions;

defined('ABSPATH') || exit;

use Elemacy\Conditions\DTO\ConditionRuleDTO;

interface ConditionInterface
{
    public function get_name(): string;

    public function get_type(): string;

    public function get_label(): string;

    /**
     * Evaluate whether the current request matches this condition.
     *
     * @param ConditionRuleDTO $rule The condition rule stored on the template.
     */
    public function check(ConditionRuleDTO $rule): bool;

    /** How the condition's value is picked in the UI: */
    public function get_value_type(): string;

    /**
     * Optional sub-values for conditions whose value type is `select`.
     * Returns an array of ['value' => string, 'label' => string] pairs.
     */
    public function get_sub_values(): array;

    /** Search configuration for conditions whose value type is `search`. */
    public function get_search_config(): array;

    /**
     * Template type slugs this condition applies to.
     * Return ['*'] to mean "applies to all template types".
     *
     * @return string[]
     */
    public function get_applicable_template_types(): array;

    /**
     * Whether this condition is offered for the given context.
     * Honours the ['*'] wildcard from {@see get_applicable_template_types()}.
     */
    public function applies_to(string $context): bool;

    /**
     * How narrowly this condition targets the request, as one of the
     * {@see Specificity} levels. Takes the stored rule because a condition's
     * reach usually depends on its value: a taxonomy archive condition with no
     * term targets a whole taxonomy, the same condition with a term targets one
     * archive.
     *
     * Only ever consulted for a condition that actually matched, and only for
     * an inclusion. Resolution compares these to decide which of several
     * matching templates of one type is the most specific.
     */
    public function get_specificity(ConditionRuleDTO $rule): int;

    /**
     * Whether this is a mock stand-in shown in the UI but not functional.
     * A mock is registered by free and overridden by an extension (e.g. pro)
     * that provides the real implementation under the same name.
     */
    public function is_mock(): bool;
}
