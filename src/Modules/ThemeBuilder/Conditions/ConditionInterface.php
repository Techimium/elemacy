<?php

namespace Elemacy\Modules\ThemeBuilder\Conditions;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\DTO\ConditionRuleDTO;

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

    /**
     * Whether this condition accepts a specific value (renders a second select in the UI).
     */
    public function has_value(): bool;

    /**
     * Optional sub-values for conditions that accept a specific value (e.g. post type).
     * Returns an array of ['value' => string, 'label' => string] pairs.
     */
    public function get_sub_values(): array;

    /**
     * Template type slugs this condition applies to.
     * Return ['*'] to mean "applies to all template types".
     *
     * @return string[]
     */
    public function get_applicable_template_types(): array;

    /**
     * Whether this condition is offered for the given template type.
     * Honours the ['*'] wildcard from {@see get_applicable_template_types()}.
     */
    public function applies_to(string $template_type): bool;

    /**
     * Whether this is a mock stand-in shown in the UI but not functional.
     * A mock is registered by free and overridden by an extension (e.g. pro)
     * that provides the real implementation under the same name.
     */
    public function is_mock(): bool;
}
