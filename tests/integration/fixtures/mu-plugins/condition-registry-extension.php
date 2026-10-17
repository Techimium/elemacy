<?php

/**
 * A condition registered the same way elemacy-pro registers its real
 * conditions (Extensions/Conditions/ProConditions.php): entirely through the
 * public Hooks::CONDITIONS_REGISTER_ACTION hook, with no edit to
 * ConditionManager, ConditionEvaluator or ConditionsBootstrap. Proves task
 * 10.1/design.md D10: a test extension can add a supported condition without
 * touching any central consumer.
 */
defined('ELEMACY_INTEGRATION_TOKEN') || exit;

// Literal hook name, not \Elemacy\Core\Hooks::CONDITIONS_REGISTER_ACTION: see
// fixtures/mu-plugins/popup-availability-suspension.php for why (mu-plugins
// load before the regular plugin autoloader is registered).
add_action('elemacy/action/conditions/register', static function ($manager): void {
    if (!class_exists('Elemacy_Fixture_Registered_Condition')) {
        class Elemacy_Fixture_Registered_Condition extends \Elemacy\Conditions\BaseCondition
        {
            public function get_name(): string
            {
                return 'elemacy_fixture/registered_condition';
            }

            public function get_type(): string
            {
                return 'general';
            }

            public function get_label(): string
            {
                return 'Fixture Registered Condition';
            }

            public function check(\Elemacy\Conditions\DTO\ConditionRuleDTO $rule): bool
            {
                return true;
            }
        }
    }

    $manager->register(new Elemacy_Fixture_Registered_Condition());
});
